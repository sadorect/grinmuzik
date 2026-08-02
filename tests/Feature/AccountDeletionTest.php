<?php

namespace Tests\Feature;

use App\Mail\AccountDeletionRequestMail;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\Concerns\SolvesCaptcha;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;
    use SolvesCaptcha;

    private function requestDeletionFor(string $email): void
    {
        Volt::test('pages.account-deletion')
            ->set('email', $email)
            ->set('captcha', $this->captchaAnswerFor('account-deletion'))
            ->call('requestDeletion');
    }

    public function test_the_page_is_reachable_without_signing_in(): void
    {
        // Google Play requires deletion to be requestable from a browser with
        // no app and no session. Putting this behind 'auth' would fail review.
        $this->get('/account/delete')
            ->assertOk()
            ->assertSee('Delete your GrinMuzik account');
    }

    public function test_it_discloses_what_is_deleted_and_what_is_kept(): void
    {
        $this->get('/account/delete')
            ->assertSee('What gets deleted')
            ->assertSee('What we keep, and why');
    }

    public function test_requesting_deletion_emails_a_confirmation_link(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->requestDeletionFor($user->email);

        Mail::assertSent(AccountDeletionRequestMail::class);

        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'status' => AccountDeletionRequest::STATUS_PENDING,
        ]);
    }

    public function test_requesting_deletion_does_not_delete_anything_yet(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->requestDeletionFor($user->email);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_an_unknown_address_looks_identical_to_a_known_one(): void
    {
        // Otherwise the page is an oracle for whether any given address has an
        // account here.
        Mail::fake();

        Volt::test('pages.account-deletion')
            ->set('email', 'nobody@example.com')
            ->set('captcha', $this->captchaAnswerFor('account-deletion'))
            ->call('requestDeletion')
            ->assertSet('submitted', true)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_the_raw_token_is_never_stored(): void
    {
        $user = User::factory()->create();

        [$request, $token] = AccountDeletionRequest::issueFor($user);

        $this->assertNotSame($token, $request->token_hash);
        $this->assertSame(hash('sha256', $token), $request->token_hash);
        $this->assertDatabaseMissing('account_deletion_requests', ['token_hash' => $token]);
    }

    public function test_the_confirmation_link_only_shows_a_confirmation_screen(): void
    {
        // A GET must never delete: mail scanners and link previewers prefetch,
        // and would otherwise destroy accounts nobody clicked on.
        $user = User::factory()->create();

        [, $token] = AccountDeletionRequest::issueFor($user);

        $this->get("/account/delete/confirm/{$token}")
            ->assertOk()
            ->assertSee('Permanently delete this account?');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_posting_the_confirmation_deletes_the_account(): void
    {
        $user = User::factory()->create();

        [, $token] = AccountDeletionRequest::issueFor($user);

        $this->post("/account/delete/confirm/{$token}")
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();

        [, $token] = AccountDeletionRequest::issueFor($user);

        $this->post("/account/delete/confirm/{$token}");

        $this->post("/account/delete/confirm/{$token}")
            ->assertRedirect(route('account.deletion'));
    }

    public function test_an_expired_token_is_refused(): void
    {
        $user = User::factory()->create();

        [$request, $token] = AccountDeletionRequest::issueFor($user);

        $request->forceFill(['expires_at' => now()->subHour()])->save();

        $this->get("/account/delete/confirm/{$token}")->assertStatus(410);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_forged_token_is_refused(): void
    {
        $user = User::factory()->create();

        AccountDeletionRequest::issueFor($user);

        $this->post('/account/delete/confirm/'.str_repeat('a', 64))
            ->assertRedirect(route('account.deletion'));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_requesting_again_invalidates_the_previous_link(): void
    {
        // Otherwise several live links accumulate across a mailbox's history.
        $user = User::factory()->create();

        [, $firstToken] = AccountDeletionRequest::issueFor($user);
        [, $secondToken] = AccountDeletionRequest::issueFor($user);

        $this->post("/account/delete/confirm/{$firstToken}")
            ->assertRedirect(route('account.deletion'));

        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->post("/account/delete/confirm/{$secondToken}");

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deleting_a_user_removes_their_uploads_but_keeps_donation_records(): void
    {
        $user = User::factory()->create();

        $artistProfile = \App\Models\ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Test Artist',
            'slug' => 'test-artist-'.$user->id,
        ]);

        $track = \App\Models\Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $artistProfile->id,
            'title' => 'A Track',
            'slug' => 'a-track-'.$user->id,
        ]);

        $donation = \App\Models\Donation::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'artist_profile_id' => $artistProfile->id,
            'amount' => 1000,
            'currency' => 'NGN',
            'type' => 'tip',
            'status' => 'completed',
        ]);

        [, $token] = AccountDeletionRequest::issueFor($user);

        $this->post("/account/delete/confirm/{$token}");

        // Uploads cascade away with the account...
        $this->assertDatabaseMissing('tracks', ['id' => $track->id]);

        // ...but the financial record survives, unlinked. This is what the
        // public disclosure promises, so a change here needs the copy in
        // AccountDeletionService::retainedItems() changed with it.
        // ...but the financial record survives, unlinked from both the donor and
        // the artist. This is what the public disclosure promises, so a change
        // here needs the copy in AccountDeletionService::retainedItems() changed
        // with it.
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'user_id' => null,
            'artist_profile_id' => null,
        ]);
    }

    public function test_deleting_an_artist_does_not_wipe_other_peoples_donation_records(): void
    {
        // donations.artist_profile_id used to cascade, so an artist closing
        // their account destroyed the payment history of everyone who had ever
        // tipped them.
        $artist = User::factory()->create();
        $donor = User::factory()->create();

        $artistProfile = \App\Models\ArtistProfile::create([
            'user_id' => $artist->id,
            'stage_name' => 'Departing Artist',
            'slug' => 'departing-artist-'.$artist->id,
        ]);

        $donation = \App\Models\Donation::create([
            'user_id' => $donor->id,
            'artist_profile_id' => $artistProfile->id,
            'amount' => 2500,
            'currency' => 'NGN',
            'type' => 'tip',
            'status' => 'completed',
        ]);

        [, $token] = AccountDeletionRequest::issueFor($artist);

        $this->post("/account/delete/confirm/{$token}");

        $this->assertDatabaseMissing('artist_profiles', ['id' => $artistProfile->id]);

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'user_id' => $donor->id,
            'amount' => 2500,
        ]);
    }

    public function test_the_deletion_page_is_linked_from_the_footer(): void
    {
        // Play requires the route to be readily discoverable, not merely to
        // exist at a URL someone has to be told.
        $this->get('/')->assertSee(route('account.deletion'), false);
    }
}
