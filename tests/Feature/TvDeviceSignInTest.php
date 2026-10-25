<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\TvDeviceCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TV sign-in by device code: the TV gets a code, the listener approves it at
 * /tv, the TV collects a listen-only token exactly once.
 */
class TvDeviceSignInTest extends TestCase
{
    use RefreshDatabase;

    private function requestCode(string $deviceName = 'Living room TV'): array
    {
        return $this->postJson('/api/tv/device-code', ['device_name' => $deviceName])
            ->assertOk()
            ->json();
    }

    private function poll(string $deviceCode)
    {
        return $this->postJson('/api/tv/token', ['device_code' => $deviceCode]);
    }

    /** Polls are rate-limited by interval; step past it between polls. */
    private function pollAfterInterval(string $deviceCode)
    {
        $this->travel(6)->seconds();

        return $this->poll($deviceCode);
    }

    private function approveAs(User $user, string $userCode): void
    {
        $this->actingAs($user);

        Volt::test('pages.tv-link')
            ->set('code', $userCode)
            ->call('findCode')
            ->assertHasNoErrors()
            ->call('approve')
            ->assertSet('outcome', 'approved');
    }

    private function createTrack(): Track
    {
        $artist = User::factory()->create();
        $profile = ArtistProfile::create([
            'user_id' => $artist->id,
            'stage_name' => 'TV Artist',
            'slug' => 'tv-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        return Track::create([
            'user_id' => $artist->id,
            'artist_profile_id' => $profile->id,
            'title' => 'TV Track',
            'slug' => 'tv-track',
            'duration' => 200,
            'is_published' => true,
        ]);
    }

    #[Test]
    public function a_tv_gets_a_readable_code_and_polling_instructions(): void
    {
        $response = $this->requestCode();

        $this->assertMatchesRegularExpression('/^[BCDFGHJKMNPQRTVWXY]{4}-[BCDFGHJKMNPQRTVWXY]{4}$/', $response['user_code']);
        $this->assertSame(64, strlen($response['device_code']));
        $this->assertSame(route('tv.link'), $response['verification_uri']);
        $this->assertSame(route('tv.link').'?code='.$response['user_code'], $response['verification_uri_complete']);
        $this->assertSame(600, $response['expires_in']);
        $this->assertSame(5, $response['interval']);

        // The device code is the TV's secret: only its hash is stored.
        $this->assertDatabaseMissing('tv_device_codes', ['device_code_hash' => $response['device_code']]);
        $this->assertDatabaseHas('tv_device_codes', ['device_code_hash' => hash('sha256', $response['device_code'])]);
    }

    #[Test]
    public function the_full_flow_signs_the_tv_in_with_a_listen_only_token(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);
        $code = $this->requestCode();

        $this->poll($code['device_code'])->assertStatus(400)->assertJson(['error' => 'authorization_pending']);

        $this->approveAs($user, strtolower(str_replace('-', '', $code['user_code'])));

        $token = $this->pollAfterInterval($code['device_code'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.name', 'Ada')
            ->json('access_token');

        $stored = $user->tokens()->sole();
        $this->assertSame('tv:Living room TV', $stored->name);
        $this->assertSame(['listen'], $stored->abilities);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/tv/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    #[Test]
    public function the_token_is_handed_out_only_once(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode();
        $this->approveAs($user, $code['user_code']);

        $this->pollAfterInterval($code['device_code'])->assertOk();
        $this->pollAfterInterval($code['device_code'])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);

        $this->assertSame(1, $user->tokens()->count());
    }

    #[Test]
    public function polling_faster_than_the_interval_is_told_to_slow_down(): void
    {
        $code = $this->requestCode();

        $this->poll($code['device_code'])->assertJson(['error' => 'authorization_pending']);
        $this->poll($code['device_code'])->assertJson(['error' => 'slow_down']);
        $this->pollAfterInterval($code['device_code'])->assertJson(['error' => 'authorization_pending']);
    }

    #[Test]
    public function a_declined_code_tells_the_tv_access_was_denied(): void
    {
        $code = $this->requestCode();
        $this->actingAs(User::factory()->create());

        Volt::test('pages.tv-link')
            ->set('code', $code['user_code'])
            ->call('findCode')
            ->call('deny')
            ->assertSet('outcome', 'denied');

        $this->pollAfterInterval($code['device_code'])->assertStatus(400)->assertJson(['error' => 'access_denied']);
    }

    #[Test]
    public function an_expired_code_cannot_be_found_or_approved(): void
    {
        $code = $this->requestCode();
        $this->actingAs(User::factory()->create());

        $page = Volt::test('pages.tv-link')
            ->set('code', $code['user_code'])
            ->call('findCode')
            ->assertHasNoErrors();

        // Found in time, but the person dithered past expiry before approving.
        $this->travel(11)->minutes();
        $page->call('approve')->assertSet('outcome', 'expired');

        Volt::test('pages.tv-link')
            ->set('code', $code['user_code'])
            ->call('findCode')
            ->assertHasErrors('code');

        $this->poll($code['device_code'])->assertStatus(400)->assertJson(['error' => 'expired_token']);
    }

    #[Test]
    public function an_unknown_code_is_rejected_and_guessing_is_rate_limited(): void
    {
        RateLimiter::clear('tv_code_entry|ip|127.0.0.1');
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            Volt::test('pages.tv-link')
                ->set('code', 'BBBB-BBBB')
                ->call('findCode')
                ->assertHasErrors('code');
        }

        // The eleventh guess is refused before the code is even looked up.
        $real = $this->requestCode();
        Volt::test('pages.tv-link')
            ->set('code', $real['user_code'])
            ->call('findCode')
            ->assertHasErrors('code')
            ->assertSet('pendingId', null);
    }

    #[Test]
    public function the_tv_page_needs_a_signed_in_user_and_keeps_the_code(): void
    {
        $this->get('/tv?code=BCDF-GHJK')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/tv?code=BCDF-GHJK')
            ->assertOk()
            ->assertSee('Sign in on your TV');
    }

    #[Test]
    public function a_tv_token_can_record_plays_but_never_reach_admin_or_mcp_routes(): void
    {
        $user = User::factory()->create();
        $track = $this->createTrack();
        $token = $user->createToken('tv:Test', [TvDeviceCode::TOKEN_ABILITY])->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/tracks/{$track->slug}/play", ['seconds_played' => 60, 'source' => 'tv'])
            ->assertOk();

        $this->withToken($token)->getJson('/api/admin/health')->assertForbidden();
        $this->withToken($token)->patchJson("/api/admin/tracks/{$track->slug}/unpublish")->assertForbidden();
        $this->withToken($token)->getJson('/api/mcp/latest-tracks')->assertForbidden();

        $this->assertTrue($track->fresh()->is_published);
    }

    #[Test]
    public function existing_write_tokens_still_work_on_listener_routes(): void
    {
        $user = User::factory()->create();
        $track = $this->createTrack();
        $token = $user->createToken('mcp-test', ['read', 'write'])->plainTextToken;

        $this->withToken($token)->postJson("/api/tracks/{$track->slug}/like")->assertOk()->assertJson(['liked' => true]);
    }

    #[Test]
    public function signing_out_on_the_tv_kills_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('tv:Test', [TvDeviceCode::TOKEN_ABILITY])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/tv/token')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function a_listener_can_see_and_sign_out_their_tvs_but_not_other_tokens(): void
    {
        $user = User::factory()->create();
        $tv = $user->createToken('tv:Bedroom', [TvDeviceCode::TOKEN_ABILITY])->accessToken;
        $mcp = $user->createToken('mcp-server', ['read', 'write'])->accessToken;
        $someoneElsesTv = User::factory()->create()->createToken('tv:Theirs', ['listen'])->accessToken;

        $this->actingAs($user);

        Volt::test('pages.tv-link')
            ->assertSee('Bedroom')
            ->assertDontSee('mcp-server')
            ->call('revoke', $mcp->id)
            ->call('revoke', $someoneElsesTv->id)
            ->call('revoke', $tv->id);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tv->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $mcp->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $someoneElsesTv->id]);
    }

    #[Test]
    public function old_codes_are_pruned(): void
    {
        $this->requestCode();
        $this->travel(2)->days();
        $this->requestCode();

        $this->artisan('model:prune', ['--model' => [TvDeviceCode::class]])->assertSuccessful();

        $this->assertSame(1, TvDeviceCode::count());
    }
}
