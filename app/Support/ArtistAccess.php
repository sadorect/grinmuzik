<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class ArtistAccess
{
    public static function setupUrl(?string $intended = null): string
    {
        $safeIntended = static::sanitizeIntended($intended);

        return route('artist.setup', array_filter([
            'upgrade' => $safeIntended ? 1 : null,
            'intended' => $safeIntended,
        ]));
    }

    public static function sanitizeIntended(?string $intended): ?string
    {
        if (! is_string($intended) || trim($intended) === '') {
            return null;
        }

        $path = (string) parse_url($intended, PHP_URL_PATH);
        $query = (string) parse_url($intended, PHP_URL_QUERY);

        if ($path === '' || ! str_starts_with($path, '/artist')) {
            return null;
        }

        return $query !== ''
            ? "{$path}?{$query}"
            : $path;
    }

    public static function promote(User $user): void
    {
        Role::findOrCreate('artist', 'web');

        if (! $user->hasRole('artist')) {
            $user->assignRole('artist');
        }
    }
}
