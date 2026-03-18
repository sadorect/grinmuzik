<?php

namespace App\Support;

class ThemePalette
{
    public const DEFAULT_PRIMARY = '#305CDE';
    public const DEFAULT_SECONDARY = '#7BA7FF';

    /**
     * @return array<int, string>
     */
    public static function palette(?string $hex, string $fallback): array
    {
        $base = static::hexToRgb(static::normalize($hex, $fallback));

        $palette = [
            50 => static::mix($base, [255, 255, 255], 0.92),
            100 => static::mix($base, [255, 255, 255], 0.84),
            200 => static::mix($base, [255, 255, 255], 0.68),
            300 => static::mix($base, [255, 255, 255], 0.48),
            400 => static::mix($base, [255, 255, 255], 0.24),
            500 => $base,
            600 => static::mix($base, [0, 0, 0], 0.12),
            700 => static::mix($base, [0, 0, 0], 0.24),
            800 => static::mix($base, [0, 0, 0], 0.38),
            900 => static::mix($base, [0, 0, 0], 0.52),
            950 => static::mix($base, [0, 0, 0], 0.68),
        ];

        return array_map(
            fn (array $rgb): string => implode(' ', $rgb),
            $palette,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function cssVariables(?string $primary = null, ?string $secondary = null): array
    {
        $variables = [];

        foreach (static::palette($primary, static::DEFAULT_PRIMARY) as $shade => $value) {
            $variables["--color-primary-{$shade}"] = $value;
        }

        foreach (static::palette($secondary, static::DEFAULT_SECONDARY) as $shade => $value) {
            $variables["--color-secondary-{$shade}"] = $value;
        }

        $variables['--color-primary-dark'] = $variables['--color-primary-600'];
        $variables['--color-secondary-dark'] = $variables['--color-secondary-600'];

        return $variables;
    }

    public static function normalize(?string $hex, string $fallback): string
    {
        $fallback = static::normalizeHex($fallback) ?? self::DEFAULT_PRIMARY;

        return static::normalizeHex($hex) ?? $fallback;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $base
     * @param  array{0: int, 1: int, 2: int}  $target
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function mix(array $base, array $target, float $weight): array
    {
        return [
            (int) round($base[0] + (($target[0] - $base[0]) * $weight)),
            (int) round($base[1] + (($target[1] - $base[1]) * $weight)),
            (int) round($base[2] + (($target[2] - $base[2]) * $weight)),
        ];
    }

    protected static function normalizeHex(?string $hex): ?string
    {
        if (! is_string($hex)) {
            return null;
        }

        $hex = strtoupper(ltrim(trim($hex), '#'));

        if (! preg_match('/^[0-9A-F]{6}$/', $hex)) {
            return null;
        }

        return "#{$hex}";
    }
}
