<?php

namespace App\Support\MediaConversion;

use Illuminate\Support\Str;
use Throwable;

/**
 * Downscales / recompresses an oversized cover image (via GD) until it fits
 * under a target byte size. Outputs JPEG. Used only after explicit consent.
 */
class ImageCompressor
{
    /** Longest-edge caps attempted in order (px). */
    public const DIMENSION_LADDER = [2000, 1600, 1280, 1024, 800];

    /** JPEG qualities attempted at each dimension, highest first. */
    public const QUALITY_LADDER = [85, 75, 65, 55];

    /**
     * @return array{path:string,size:int,width:int,height:int}|null
     */
    public function compressToUnder(string $sourcePath, int $maxBytes, string $workDir): ?array
    {
        if (! is_file($sourcePath) || $maxBytes <= 0 || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        if (! is_dir($workDir)) {
            @mkdir($workDir, 0755, true);
        }

        try {
            $source = @imagecreatefromstring((string) file_get_contents($sourcePath));

            if ($source === false) {
                return null;
            }

            $origW = imagesx($source);
            $origH = imagesy($source);
            $longest = max($origW, $origH);

            foreach (self::DIMENSION_LADDER as $cap) {
                // Never upscale; skip caps larger than the source.
                if ($cap > $longest && $cap !== self::DIMENSION_LADDER[array_key_last(self::DIMENSION_LADDER)]) {
                    if ($cap >= $longest) {
                        $cap = $longest;
                    }
                }

                $scale = min(1.0, $cap / max(1, $longest));
                $newW = max(1, (int) round($origW * $scale));
                $newH = max(1, (int) round($origH * $scale));

                $canvas = imagecreatetruecolor($newW, $newH);
                // White background so JPEG has no black transparency artifacts.
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $newW, $newH, $white);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

                foreach (self::QUALITY_LADDER as $quality) {
                    $outPath = rtrim($workDir, '/').'/'.Str::uuid()->toString().'.jpg';

                    if (! @imagejpeg($canvas, $outPath, $quality)) {
                        @unlink($outPath);

                        continue;
                    }

                    $size = filesize($outPath) ?: PHP_INT_MAX;

                    if ($size <= $maxBytes) {
                        imagedestroy($canvas);
                        imagedestroy($source);

                        return ['path' => $outPath, 'size' => $size, 'width' => $newW, 'height' => $newH];
                    }

                    @unlink($outPath);
                }

                imagedestroy($canvas);
            }

            imagedestroy($source);
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
