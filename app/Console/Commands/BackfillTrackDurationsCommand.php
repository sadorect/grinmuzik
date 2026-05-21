<?php

namespace App\Console\Commands;

use App\Models\Track;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class BackfillTrackDurationsCommand extends Command
{
    protected $signature = 'tracks:backfill-durations
        {--all : Re-process all tracks, not just those with duration = 0}
        {--id=* : Only process specific track IDs}';

    protected $description = 'Extract and store audio duration for tracks that are missing it.';

    public function handle(): int
    {
        // Pre-flight: confirm ffprobe is reachable
        $whichProcess = new Process(['which', 'ffprobe']);
        $whichProcess->run();
        $ffprobeBin = trim($whichProcess->getOutput()) ?: null;

        if (! $ffprobeBin) {
            // Try common fixed paths
            foreach (['/usr/bin/ffprobe', '/usr/local/bin/ffprobe'] as $candidate) {
                if (file_exists($candidate)) {
                    $ffprobeBin = $candidate;
                    break;
                }
            }
        }

        if (! $ffprobeBin) {
            $this->components->error('ffprobe not found. Install ffmpeg on this server.');
            return self::FAILURE;
        }

        $this->components->info("Using ffprobe: {$ffprobeBin}");

        $query = Track::query()->with('media');

        if ($this->option('id')) {
            $query->whereIn('id', $this->option('id'));
        } elseif (! $this->option('all')) {
            $query->where('duration', 0)->orWhereNull('duration');
        }

        $tracks = $query->get();

        if ($tracks->isEmpty()) {
            $this->components->info('No tracks to process.');
            return self::SUCCESS;
        }

        $this->components->info("Processing {$tracks->count()} track(s)...");

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($tracks as $track) {
            $media = $track->getFirstMedia('audio');

            if (! $media) {
                $this->components->warn("  [{$track->id}] {$track->title} — no audio media, skipping.");
                $skipped++;
                continue;
            }

            $this->getOutput()->write("  [{$track->id}] {$track->title} ... ");

            $tempPath = null;

            try {
                $disk     = $media->disk;
                $fullPath = $media->getPath(); // absolute path (organized layout)
                $ext      = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)) ?: 'audio';
                $tempPath = null;

                // Resolve the actual file path — try the organized layout first,
                // then fall back to the legacy Spatie default ({disk_root}/{id}/{file_name}).
                if (file_exists($fullPath)) {
                    $sourcePath   = $fullPath;
                    $needsCleanup = false;
                } else {
                    $diskRoot     = Storage::disk($disk)->path('');
                    $legacyPath   = rtrim($diskRoot, '/') . '/' . $media->id . '/' . $media->file_name;

                    if (file_exists($legacyPath)) {
                        $sourcePath   = $legacyPath;
                        $needsCleanup = false;
                    } else {
                        // Remote disk — stream to a temp file
                        $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.' . $ext;
                        $stream   = Storage::disk($disk)->readStream($media->getPathRelativeToRoot());

                        if (! $stream) {
                            throw new \RuntimeException('File not found locally and could not stream remotely.');
                        }

                        $dest = fopen($tempPath, 'wb');
                        stream_copy_to_stream($stream, $dest);
                        fclose($dest);
                        fclose($stream);
                        $sourcePath   = $tempPath;
                        $needsCleanup = true;
                    }
                }

                [$duration, $ffError] = $this->ffprobeDuration($sourcePath, $ffprobeBin);

                if ($needsCleanup && file_exists($tempPath)) {
                    @unlink($tempPath);
                }

                if ($duration > 0) {
                    $track->update(['duration' => $duration]);
                    $this->getOutput()->writeln("<info>{$duration}s</info>");
                    $updated++;
                } else {
                    $this->getOutput()->writeln('<comment>0s — ffprobe returned nothing</comment>');
                    $this->getOutput()->writeln("      path: {$sourcePath}");
                    if ($ffError) {
                        $this->getOutput()->writeln('      ffprobe stderr: ' . trim($ffError));
                    }
                    if (! file_exists($sourcePath)) {
                        $this->getOutput()->writeln('      WARNING: file does not exist at that path');
                    } else {
                        $this->getOutput()->writeln('      file size: ' . filesize($sourcePath) . ' bytes');
                    }
                    $skipped++;
                }
            } catch (Throwable $e) {
                if ($tempPath && file_exists($tempPath)) {
                    @unlink($tempPath);
                }
                $this->getOutput()->writeln('<error>ERROR: ' . $e->getMessage() . '</error>');
                $failed++;
            }
        }

        $this->newLine();
        $this->components->info("Done. Updated: {$updated} | Skipped: {$skipped} | Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{int, string} [duration_seconds, stderr] */
    private function ffprobeDuration(string $path, string $bin): array
    {
        $process = new Process([
            $bin, '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);
        $process->setTimeout(30);
        $process->run();

        $output = trim($process->getOutput());
        $stderr = $process->getErrorOutput();

        $duration = $output !== '' ? max(0, (int) round((float) $output)) : 0;

        return [$duration, $stderr];
    }
}
