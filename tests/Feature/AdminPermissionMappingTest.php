<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteBranding;
use App\Filament\Resources\ArtistProfileResource;
use App\Filament\Resources\PermissionResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\TrackResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPermissionMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_limited_admin_permissions_only_unlock_the_matching_resources(): void
    {
        $admin = $this->createAdminWithPermissions([
            'view users',
            'view tracks',
        ]);

        $this->actingAs($admin);

        $this->assertTrue(UserResource::canViewAny());
        $this->assertFalse(UserResource::canCreate());
        $this->assertTrue(TrackResource::canViewAny());
        $this->assertFalse(TrackResource::canCreate());
        $this->assertFalse(SiteBranding::canAccess());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(AdminStatsOverview::canView());
    }

    public function test_moderation_permissions_do_not_grant_full_edit_access(): void
    {
        $admin = $this->createAdminWithPermissions([
            'approve artists',
            'reject artists',
            'approve tracks',
            'reject tracks',
        ]);

        $this->actingAs($admin);

        $this->assertTrue(ArtistProfileResource::canViewAny());
        $this->assertFalse(ArtistProfileResource::canEdit(new ArtistProfile()));
        $this->assertTrue(TrackResource::canViewAny());
        $this->assertFalse(TrackResource::canEdit(new Track()));
    }

    public function test_admin_cannot_edit_super_admin_accounts_even_with_user_edit_permissions(): void
    {
        $admin = $this->createAdminWithPermissions([
            'view users',
            'edit users',
        ]);

        $superAdmin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $superAdmin->assignRole('super_admin');

        $this->actingAs($admin);

        $this->assertFalse(UserResource::canEdit($superAdmin));
    }

    public function test_super_admin_bypasses_fine_grained_permission_checks(): void
    {
        $superAdmin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(PermissionResource::canCreate());
        $this->assertTrue(SiteBranding::canAccess());
        $this->assertTrue(AdminStatsOverview::canView());
    }

    protected function createAdminWithPermissions(array $permissions): User
    {
        $role = Role::findOrCreate('admin', 'web');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
