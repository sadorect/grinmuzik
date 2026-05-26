<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use App\Support\Charts\ChartSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChartHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function charts_page_can_render_a_frozen_weekly_snapshot(): void
    {
        Cache::flush();
        CarbonImmutable::setTestNow('2026-05-26 12:00:00');

        [$user, $profile, $track] = $this->createArtistWithTrack();

        PlayHistory::forceCreate([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'ip_address' => '127.0.0.1',
            'source' => 'web',
            'created_at' => '2026-05-20 10:00:00',
            'updated_at' => '2026-05-20 10:00:00',
        ]);

        $snapshot = app(ChartSnapshotService::class)->createSnapshot('week', CarbonImmutable::parse('2026-05-20'), true);

        $response = $this->get(route('charts', ['timeframe' => 'week', 'snapshot' => $snapshot->period_key]))
            ->assertOk()
            ->assertSee('Historical Record')
            ->assertSee($snapshot->display_title)
            ->assertSee($track->title);

        $this->assertStringContainsString(route('artist.page', $profile), (string) $response->getContent());
    }

    private function createArtistWithTrack(): array
    {
        $user = User::factory()->create();

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Archive Artist',
            'slug' => 'archive-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Locked Chart Song',
            'slug' => 'locked-chart-song',
            'is_published' => true,
            'is_free' => true,
            'downloads_count' => 14,
        ]);

        return [$user, $profile, $track];
    }
}