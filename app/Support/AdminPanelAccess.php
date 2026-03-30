<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

class AdminPanelAccess
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function allows(array|string|null $permissions = null, bool $superAdminOnly = false): bool
    {
        $user = static::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($superAdminOnly) {
            return false;
        }

        $permissions = array_values(array_filter(
            Arr::wrap($permissions),
            static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
        ));

        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
