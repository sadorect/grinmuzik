<?php

namespace App\Support\MediaConversion;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Re-encodes an oversized audio file to progressively lower MP3 bitrates until
 * it fits under a target byte size. Lossy by design — used only after the
 * uploader has explicitly consented to automatic conversion.
 */
class AudioCompressor
{
    /** Bitrates (kbps) attempted in order, highest quality first. */
    public const BITRATE_LADDER = [256, 192, 128];

    /**
     * @return array{path:string,bitrate:int,size:int}|null  Null if it cannot be brought under $maxBytes.
     */
    public function compressToUnder(string $sourcePath, int $maxBytes, string $workDir): ?array
    {
        if (! is_file($sourcePath) || $maxBytes <= 0) {
            return null;
        }

        if (! is_dir($workDir)) {
            @mkdir($workDir, 0755, true);
        }

        // Estimate the highest bitrate that can fit, so we normally do a single
        // encode instead of stepping down through the whole ladder.
        $candidates = self::BITRATE_LADDER;
        $duration = $this->probeDurationSeconds($sourcePath);

        if ($duration > 0) {
            $neededKbps = (int) floor(($maxBytes * 8) / $duration / 1000 * 0.95);
            $fitting = array_values(array_filter(self::BITRATE_LADDER, fn (int $b): bool => $b <= $neededKbps));
            $candidates = $fitting !== [] ? $fitting : [min(self::BITRATE_LADDER)];
        }

        foreach ($candidates as $bitrate) {
            $outPath = rtrim($workDir, '/').'/'.Str::uuid()->toString().'.mp3';

            try {
                $process = new Process([
                    'ffmpeg', '-y',
                    '-i', $sourcePath,
                    '-vn',                       // drop any embedded artwork/video stream
                    '-c:a', 'libmp3lame',
                    '-b:a', $bitrate.'k',
                    '-map_metadata', '0',        // preserve tags
                    $outPath,
                ]);
                $process->setTimeout(600);
                $process->run();

                if (! $process->isSuccessful() || ! is_file($outPath)) {
                    @unlink($outPath);

                    continue;
                }

                $size = filesize($outPath) ?: PHP_INT_MAX;

                if ($size <= $maxBytes) {
                    return ['path' => $outPath, 'bitrate' => $bitrate, 'size' => $size];
                }

                // Too big at this bitrate — discard and step down.
                @unlink($outPath);
            } catch (Throwable) {
                @unlink($outPath);

                continue;
            }
        }

        return null;
    }

    protected function probeDurationSeconds(string $path): float
    {
        try {
            $process = new Process([
                'ffprobe', '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $path,
            ]);
            $process->setTimeout(30);
            $process->run();

            return max(0.0, (float) trim($process->getOutput()));
        } catch (Throwable) {
            return 0.0;
        }
    }
}
