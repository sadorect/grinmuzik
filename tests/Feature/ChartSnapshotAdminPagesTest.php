<?php

namespace Tests\Feature;

use App\Filament\Resources\ChartSnapshotResource;
use App\Models\ArtistProfile;
use App\Models\ChartSnapshot;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use App\Support\Charts\ChartSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChartSnapshotAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function admin_can_render_chart_history_list_and_view_pages(): void
    {
        CarbonImmutable::setTestNow('2026-05-26 12:00:00');

        $admin = $this->createAnalyticsAdmin();
        [$user, $track] = $this->createPublishedTrack();

        PlayHistory::forceCreate([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'ip_address' => '127.0.0.1',
            'source' => 'web',
            'created_at' => '2026-05-20 10:00:00',
            'updated_at' => '2026-05-20 10:00:00',
        ]);

        $snapshot = app(ChartSnapshotService::class)->createSnapshot('week', CarbonImmutable::parse('2026-05-20'), true);

        $this->actingAs($admin)
            ->get(ChartSnapshotResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Capture Snapshot')
            ->assertSee($snapshot->display_title)
            ->assertSee($track->title);

        $this->actingAs($admin)
            ->get(ChartSnapshotResource::getUrl('view', ['record' => $snapshot]))
            ->assertOk()
            ->assertSee('Capture Snapshot')
            ->assertSee($snapshot->display_title)
            ->assertSee('immutable once written', false)
            ->assertSee($track->title);
    }

    #[Test]
    public function admin_snapshot_trigger_creates_and_skips_immutable_periods(): void
    {
        CarbonImmutable::setTestNow('2026-05-27 12:00:00');

        [$user, $track] = $this->createPublishedTrack();

        PlayHistory::forceCreate([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'ip_address' => '127.0.0.1',
            'source' => 'web',
            'created_at' => '2026-05-20 10:00:00',
            'updated_at' => '2026-05-20 10:00:00',
        ]);

        $first = ChartSnapshotResource::captureSnapshotsFromAdmin([
            'period' => 'week',
            'date' => '2026-05-20',
        ]);

        $second = ChartSnapshotResource::captureSnapshotsFromAdmin([
            'period' => 'week',
            'date' => '2026-05-20',
        ]);

        $this->assertDatabaseCount('chart_snapshots', 1);
        $this->assertSame(['Week of May 18, 2026'], $first['created']);
        $this->assertSame([], $first['skipped']);
        $this->assertSame([], $second['created']);
        $this->assertSame(['Week of May 18, 2026'], $second['skipped']);

        $snapshot = ChartSnapshot::query()->firstOrFail();
        $this->assertSame(1, $snapshot->entries()->count());
    }

    private function createAnalyticsAdmin(): User
    {
        $role = Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('view analytics', 'web');
        $role->syncPermissions(['view analytics']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createPublishedTrack(): array
    {
        $user = User::factory()->create();

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Admin Archive Artist',
            'slug' => 'admin-archive-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Admin Snapshot Song',
            'slug' => 'admin-snapshot-song',
            'is_published' => true,
            'is_free' => true,
        ]);

        return [$user, $track];
    }
}