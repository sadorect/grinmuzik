<?php

namespace Tests\Feature;

use App\Filament\Resources\ArtistProfileResource\Pages\ListArtistProfiles;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\ArtistProfile;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Impersonation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, array $attributes = []): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function superAdmin(): User
    {
        return $this->userWithRole('super_admin', ['name' => 'Owner']);
    }

    private function artist(): User
    {
        return $this->userWithRole('artist', ['name' => 'Abiodun Bibiire']);
    }

    private function service(): Impersonation
    {
        return app(Impersonation::class);
    }

    public function test_super_admin_can_sign_in_as_an_artist(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $this->actingAs($owner);

        $this->service()->start($owner, $artist, '/musicdirector/users');

        $this->assertTrue(auth()->user()->is($artist));
        $this->assertSame($owner->id, session(Impersonation::SESSION_KEY));
        $this->assertTrue($this->service()->impersonator()->is($owner));

        $row = ActivityLog::where('event', 'impersonation.started')->sole();
        $this->assertSame($owner->id, $row->actor_id);
        $this->assertSame($artist->id, $row->subject_id);
    }

    public function test_the_rules(): void
    {
        $owner = $this->superAdmin();
        $admin = $this->userWithRole('admin');
        $otherOwner = $this->userWithRole('super_admin');
        $artist = $this->artist();
        $listener = $this->userWithRole('listener');
        $service = $this->service();

        $this->assertTrue($service->canImpersonate($owner, $artist));
        $this->assertTrue($service->canImpersonate($owner, $listener));

        $this->assertSame('Only the super admin can sign in as other users.', $service->denialReason($admin, $artist));
        $this->assertSame('Only the super admin can sign in as other users.', $service->denialReason($artist, $listener));
        $this->assertSame('That is your own account.', $service->denialReason($owner, $owner));
        $this->assertSame('Admin accounts cannot be signed in as.', $service->denialReason($owner, $admin));
        $this->assertSame('Admin accounts cannot be signed in as.', $service->denialReason($owner, $otherOwner));
        $this->assertNotNull($service->denialReason(null, $artist));
    }

    public function test_cannot_nest(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $listener = $this->userWithRole('listener');
        $this->actingAs($owner);

        $this->service()->start($owner, $artist);

        $this->assertStringContainsString('already signed in as someone else', $this->service()->denialReason($owner, $listener));
    }

    public function test_start_refuses_a_denied_pair_and_changes_nothing(): void
    {
        $admin = $this->userWithRole('admin');
        $artist = $this->artist();
        $this->actingAs($admin);

        try {
            $this->service()->start($admin, $artist);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException) {
        }

        $this->assertTrue(auth()->user()->is($admin));
        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));
        $this->assertSame(0, ActivityLog::where('event', 'like', 'impersonation.%')->count());
    }

    public function test_banner_shows_only_while_signed_in_as_someone(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();

        $this->actingAs($owner)->get(route('about'))->assertOk()->assertDontSee('Return to my account');

        $this->service()->start($owner, $artist);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Signed in as Abiodun Bibiire')
            ->assertSee('Return to my account')
            ->assertSee(route('impersonation.stop'), false);
    }

    public function test_return_to_my_account(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $this->actingAs($owner);
        $this->service()->start($owner, $artist, '/musicdirector/users');

        $this->post(route('impersonation.stop'))->assertRedirect('/musicdirector/users');

        $this->assertTrue(auth()->user()->is($owner));
        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));

        $row = ActivityLog::where('event', 'impersonation.stopped')->sole();
        $this->assertSame($owner->id, $row->actor_id);
        $this->assertSame($artist->id, $row->subject_id);
    }

    public function test_logging_out_while_signed_in_as_someone_returns_to_own_account(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $this->actingAs($owner);
        $this->service()->start($owner, $artist, '/musicdirector/users');

        $this->post(route('logout'))->assertRedirect('/musicdirector/users');

        $this->assertAuthenticatedAs($owner);
    }

    public function test_normal_logout_still_logs_out(): void
    {
        $artist = $this->artist();

        $this->actingAs($artist)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_stop_without_impersonation_does_nothing_harmful(): void
    {
        $artist = $this->artist();

        $this->actingAs($artist)->post(route('impersonation.stop'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($artist);
    }

    public function test_actions_taken_while_signed_in_as_someone_name_the_admin(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $this->actingAs($owner);
        $this->service()->start($owner, $artist);

        $row = app(AuditLogger::class)->record('track.deleted', $artist, ['title' => 'x']);

        $this->assertSame($artist->id, $row->actor_id);
        $this->assertSame(['id' => $owner->id, 'name' => 'Owner'], $row->properties['impersonated_by']);
        $this->assertSame('x', $row->properties['title']);
    }

    public function test_normal_actions_are_not_tagged(): void
    {
        $artist = $this->artist();
        $this->actingAs($artist);

        $row = app(AuditLogger::class)->record('track.deleted', $artist, ['title' => 'x']);

        $this->assertArrayNotHasKey('impersonated_by', $row->properties);
    }

    public function test_browsing_as_someone_does_not_mark_them_online(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $this->actingAs($owner);
        $this->service()->start($owner, $artist);

        $this->get(route('about'))->assertOk();

        $this->assertNull($artist->fresh()->last_seen_at);
    }

    public function test_user_list_button_signs_in_and_goes_to_their_dashboard(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $this->actingAs($owner);

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('impersonate')->table($artist))
            ->assertActionHidden(TestAction::make('impersonate')->table($owner))
            ->callAction(TestAction::make('impersonate')->table($artist))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($artist);
        $this->assertSame(UserResource::getUrl('index'), session(Impersonation::RETURN_KEY));
    }

    public function test_lower_admins_do_not_get_the_button(): void
    {
        $admin = $this->userWithRole('admin');
        \Spatie\Permission\Models\Permission::findOrCreate('manage users', 'web');
        $admin->givePermissionTo('manage users');
        $artist = $this->artist();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertSuccessful()
            ->assertActionHidden(TestAction::make('impersonate')->table($artist));
    }

    public function test_artist_list_button_signs_in_as_the_artists_account(): void
    {
        $owner = $this->superAdmin();
        $artist = $this->artist();
        $profile = ArtistProfile::create([
            'user_id' => $artist->id,
            'stage_name' => 'Abiodun Bibiire',
            'slug' => 'abiodun-bibiire',
            'is_approved' => true,
            'is_active' => true,
        ]);
        $this->actingAs($owner);

        Livewire::test(ListArtistProfiles::class)
            ->callAction(TestAction::make('impersonate')->table($profile))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($artist);
    }
}
