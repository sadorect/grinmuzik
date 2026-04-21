<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistUpgradeFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function listener_visiting_the_upload_page_is_redirected_into_the_artist_upgrade_flow(): void
    {
        $user = User::factory()->create();

        Role::findOrCreate('listener', 'web');
        $user->assignRole('listener');

        $this->actingAs($user)
            ->get(route('artist.upload-track'))
            ->assertRedirect(route('artist.setup', [
                'upgrade' => 1,
                'intended' => '/artist/tracks/upload',
            ]));
    }

    #[Test]
    public function listener_can_enable_artist_access_from_the_setup_page(): void
    {
        $user = User::factory()->create();

        Role::findOrCreate('listener', 'web');
        $user->assignRole('listener');

        $this->actingAs($user)
            ->get(route('artist.setup'))
            ->assertOk()
            ->assertSee('This account is still set up as a listener.');

        Volt::test('pages.artist.setup')
            ->call('upgradeToArtist')
            ->assertRedirect(route('artist.setup'));

        $this->assertTrue($user->fresh()->hasRole('artist'));
    }
}
