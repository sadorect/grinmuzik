<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\ChartSnapshot;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChartSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chart_snapshot_command_creates_immutable_weekly_and_monthly_records(): void
    {
        CarbonImmutable::setTestNow('2026-05-26 12:00:00');

        [$user, $track] = $this->createPublishedTrack();

        PlayHistory::forceCreate([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'ip_address' => '127.0.0.1',
            'source' => 'web',
            'created_at' => '2026-05-20 10:00:00',
            'updated_at' => '2026-05-20 10:00:00',
        ]);

        PlayHistory::forceCreate([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'ip_address' => '127.0.0.1',
            'source' => 'web',
            'created_at' => '2026-04-10 08:30:00',
            'updated_at' => '2026-04-10 08:30:00',
        ]);

        $this->artisan('charts:snapshot', ['period' => 'week', '--date' => '2026-05-20'])
            ->assertSuccessful();

        $this->artisan('charts:snapshot', ['period' => 'month', '--date' => '2026-04-10'])
            ->assertSuccessful();

        $this->artisan('charts:snapshot', ['period' => 'week', '--date' => '2026-05-20'])
            ->assertSuccessful();

        $this->assertDatabaseCount('chart_snapshots', 2);
        $this->assertDatabaseCount('chart_snapshot_entries', 2);

        $weeklySnapshot = ChartSnapshot::query()->where('period_type', ChartSnapshot::PERIOD_WEEK)->firstOrFail();
        $monthlySnapshot = ChartSnapshot::query()->where('period_type', ChartSnapshot::PERIOD_MONTH)->firstOrFail();

        $this->assertSame(1, $weeklySnapshot->total_tracks);
        $this->assertSame(1, $monthlySnapshot->total_tracks);
        $this->assertSame(1, $weeklySnapshot->entries()->count());
        $this->assertSame(1, $monthlySnapshot->entries()->count());
        $this->assertSame($track->id, $weeklySnapshot->entries()->firstOrFail()->track_id);
        $this->assertSame($track->id, $monthlySnapshot->entries()->firstOrFail()->track_id);
    }

    private function createPublishedTrack(): array
    {
        $user = User::factory()->create();

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Snapshot Artist',
            'slug' => 'snapshot-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Immutable Cut',
            'slug' => 'immutable-cut',
            'is_published' => true,
            'is_free' => true,
            'downloads_count' => 12,
        ]);

        return [$user, $track];
    }
}