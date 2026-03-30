<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_frontend_requests_update_last_seen_timestamp(): void
    {
        $user = User::factory()->create([
            'last_seen_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('about'))
            ->assertOk();

        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_online_scope_only_includes_recently_active_users(): void
    {
        $onlineUser = User::factory()->create([
            'last_seen_at' => now()->subMinutes(2),
        ]);

        $offlineUser = User::factory()->create([
            'last_seen_at' => now()->subMinutes(10),
        ]);

        User::factory()->create([
            'last_seen_at' => null,
        ]);

        $this->assertTrue($onlineUser->fresh()->is_online);
        $this->assertFalse($offlineUser->fresh()->is_online);
        $this->assertSame([$onlineUser->id], User::query()->online()->pluck('id')->all());
    }
}
