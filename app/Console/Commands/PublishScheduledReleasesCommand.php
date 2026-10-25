<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Track;
use App\Support\ArtistActivityNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishScheduledReleasesCommand extends Command
{
    protected $signature = 'releases:publish-scheduled';

    protected $description = 'Publish tracks and albums whose scheduled release time has arrived.';

    public function handle(ArtistActivityNotifier $notifier): int
    {
        $albums = 0;
        $tracks = 0;

        // Albums first: releasing one also releases the tracks scheduled with it,
        // and those are reported in the album's email instead of one each.
        Album::query()->dueForRelease()->orderBy('scheduled_release_at')->each(function (Album $album) use ($notifier, &$albums, &$tracks): void {
            $before = $album->tracks()->published()->count();

            if (! $this->attempt(fn () => $album->releaseIfDue(), 'album', $album->id)) {
                return;
            }

            $withAlbum = $album->tracks()->published()->count() - $before;

            $albums++;
            $tracks += $withAlbum;
            $notifier->sendScheduledReleaseLive($album->fresh(), $withAlbum);
        });

        Track::query()->dueForRelease()->orderBy('scheduled_release_at')->each(function (Track $track) use ($notifier, &$tracks): void {
            if (! $this->attempt(fn () => $track->releaseIfDue(), 'track', $track->id)) {
                return;
            }

            $tracks++;
            $notifier->sendScheduledReleaseLive($track->fresh());
        });

        if ($albums + $tracks > 0) {
            Log::info('Scheduled releases published', ['albums' => $albums, 'tracks' => $tracks]);
        }

        $this->components->info("Released {$albums} album(s) and {$tracks} track(s).");

        return self::SUCCESS;
    }

    /** One broken release must not hold up the rest of the queue. */
    protected function attempt(callable $release, string $type, int $id): bool
    {
        try {
            return $release();
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Scheduled release failed', ['type' => $type, 'id' => $id, 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
