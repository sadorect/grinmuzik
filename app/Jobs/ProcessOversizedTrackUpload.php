<?php

namespace App\Jobs;

use App\Models\Track;
use App\Models\User;
use App\Support\ArtistActivityNotifier;
use App\Support\MediaConversion\AudioCompressor;
use App\Support\MediaConversion\ImageCompressor;
use App\Support\UploadLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Converts a consented oversized upload down to the current limits, then creates
 * the track and attaches its media — off the request cycle. Notifies the artist
 * on success or failure. If a file cannot be brought under the limit, the whole
 * upload is discarded (no draft) and the artist is asked to re-upload.
 */
class ProcessOversizedTrackUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** No retries — conversion is deterministic; a retry would duplicate the track. */
    public int $tries = 1;

    /** Kept under the queue worker's --timeout (120s) and retry_after (90s). */
    public int $timeout = 115;

    /**
     * @param  array<string,mixed>  $trackData  Fillable Track attributes (no user/profile ids).
     */
    public function __construct(
        public int $userId,
        public array $trackData,
        public string $audioRel,
        public string $audioOriginalName,
        public ?string $coverRel = null,
        public ?string $coverOriginalName = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        $profile = $user?->artistProfile;

        if (! $user || ! $profile) {
            $this->cleanupStaged();

            return;
        }

        // Keep Media Library's ceiling aligned with the admin-configurable limits.
        config(['media-library.max_file_size' => max(UploadLimits::audioBytes(), UploadLimits::imageBytes())]);

        $local = Storage::disk('local');
        $workRel = 'tmp/oversized-work/'.Str::uuid()->toString();
        $workAbs = $local->path($workRel);
        $title = (string) ($this->trackData['title'] ?? 'track');

        try {
            // --- Audio: convert only if over the limit ---
            $audioAbs = $local->path($this->audioRel);

            if (! is_file($audioAbs)) {
                throw new \RuntimeException('Staged audio file is missing.');
            }

            if (filesize($audioAbs) > UploadLimits::audioBytes()) {
                $converted = app(AudioCompressor::class)->compressToUnder($audioAbs, UploadLimits::audioBytes(), $workAbs);

                if (! $converted) {
                    $this->notifyFailed($user, $title, 'audio', UploadLimits::formatKilobytes(UploadLimits::audioKb()));
                    $this->cleanup($workAbs);

                    return;
                }

                $audioImportRel = $workRel.'/'.basename($converted['path']);
                $audioFileName = (Str::slug($title) ?: 'track').'.mp3';
            } else {
                $audioImportRel = $this->audioRel;
                $ext = strtolower(pathinfo($this->audioOriginalName, PATHINFO_EXTENSION) ?: 'mp3');
                $audioFileName = (Str::slug($title) ?: 'track').'.'.$ext;
            }

            // --- Cover (optional): convert if over the limit; drop it if unconvertible ---
            $coverImportRel = null;
            $coverFileName = null;

            if ($this->coverRel) {
                $coverAbs = $local->path($this->coverRel);

                if (is_file($coverAbs)) {
                    if (filesize($coverAbs) > UploadLimits::imageBytes()) {
                        $cover = app(ImageCompressor::class)->compressToUnder($coverAbs, UploadLimits::imageBytes(), $workAbs);

                        if ($cover) {
                            $coverImportRel = $workRel.'/'.basename($cover['path']);
                            $coverFileName = 'cover.jpg';
                        }
                    } else {
                        $coverImportRel = $this->coverRel;
                        $ext = strtolower(pathinfo((string) $this->coverOriginalName, PATHINFO_EXTENSION) ?: 'jpg');
                        $coverFileName = 'cover.'.$ext;
                    }
                }
            }

            // --- Create the track and attach media ---
            $track = null;

            DB::transaction(function () use ($user, $profile, $local, $audioImportRel, $audioFileName, $coverImportRel, $coverFileName, &$track): void {
                $track = Track::create(array_merge($this->trackData, [
                    'user_id' => $user->id,
                    'artist_profile_id' => $profile->id,
                ]));

                $track->addMediaFromDisk($audioImportRel, 'local')
                    ->usingFileName($audioFileName)
                    ->toMediaCollection('audio');

                $duration = $this->probeDuration($local->path($audioImportRel));

                if ($duration > 0) {
                    $track->update(['duration' => $duration]);
                }

                if ($coverImportRel) {
                    $track->addMediaFromDisk($coverImportRel, 'local')
                        ->usingFileName($coverFileName)
                        ->toMediaCollection('cover');
                }
            });

            app(ArtistActivityNotifier::class)->sendTrackConversionCompleted(
                $track->fresh(['artistProfile.user', 'genre', 'album']),
            );
        } catch (Throwable $exception) {
            report($exception);

            if (isset($track) && $track?->exists) {
                $track->delete();
            }

            $this->notifyFailed($user, $title, 'processing', null);
        } finally {
            $this->cleanup($workAbs);
        }
    }

    protected function probeDuration(string $path): int
    {
        try {
            if (! is_file($path)) {
                return 0;
            }

            $process = new Process([
                'ffprobe', '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $path,
            ]);
            $process->setTimeout(30);
            $process->run();

            $output = trim($process->getOutput());

            return $output !== '' ? max(0, (int) round((float) $output)) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    protected function notifyFailed(User $user, string $title, string $stage, ?string $limit): void
    {
        try {
            app(ArtistActivityNotifier::class)->sendTrackConversionFailed($user, $title, $stage, $limit);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function cleanupStaged(): void
    {
        $local = Storage::disk('local');

        foreach (array_filter([$this->audioRel, $this->coverRel]) as $rel) {
            try {
                $local->delete($rel);
            } catch (Throwable) {
                // best-effort
            }
        }
    }

    protected function cleanup(string $workAbs): void
    {
        $this->cleanupStaged();

        try {
            if (is_dir($workAbs)) {
                foreach ((array) glob($workAbs.'/*') as $file) {
                    @unlink($file);
                }

                @rmdir($workAbs);
            }
        } catch (Throwable) {
            // best-effort
        }
    }
}
