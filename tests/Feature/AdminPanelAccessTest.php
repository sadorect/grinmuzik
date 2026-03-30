<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_can_access_the_admin_panel(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/musicdirector')
            ->assertOk();
    }

    public function test_non_admin_users_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('listener', 'web');
        $user->assignRole('listener');

        $this->actingAs($user)
            ->get('/musicdirector')
            ->assertForbidden()
            ->assertSee('Access restricted');
    }

    public function test_admins_see_the_admin_panel_link_in_the_main_shell(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('about'))
            ->assertOk()
            ->assertSee('Admin Panel');
    }

    public function test_lower_admins_do_not_see_super_admin_accounts_in_user_resource_queries(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create([
            'email' => 'super-admin@example.com',
        ]);
        Role::findOrCreate('super_admin', 'web');
        $superAdmin->assignRole('super_admin');

        $normalUser = User::factory()->create([
            'email' => 'normal-user@example.com',
        ]);

        $this->actingAs($admin);

        $emails = UserResource::getEloquentQuery()
            ->pluck('email')
            ->all();

        $this->assertContains($normalUser->email, $emails);
        $this->assertNotContains($superAdmin->email, $emails);
    }
}
