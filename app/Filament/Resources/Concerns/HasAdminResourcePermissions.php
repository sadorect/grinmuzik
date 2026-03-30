<?php

namespace App\Filament\Resources\Concerns;

use App\Support\AdminPanelAccess;
use Illuminate\Database\Eloquent\Model;

trait HasAdminResourcePermissions
{
    protected static function allowsResourceAccess(array|string|null $permissions = null): bool
    {
        return AdminPanelAccess::allows($permissions, static::superAdminOnly());
    }

    protected static function permissionConfig(string $property): array|string|null
    {
        return property_exists(static::class, $property)
            ? static::${$property}
            : null;
    }

    protected static function superAdminOnly(): bool
    {
        return (bool) static::permissionConfig('superAdminOnly');
    }

    public static function canViewAny(): bool
    {
        return static::allowsResourceAccess(static::permissionConfig('viewAnyPermissions') ?? static::permissionConfig('viewPermissions'));
    }

    public static function canView(Model $record): bool
    {
        return static::allowsResourceAccess(static::permissionConfig('viewPermissions') ?? static::permissionConfig('viewAnyPermissions'));
    }

    public static function canCreate(): bool
    {
        return static::allowsResourceAccess(static::permissionConfig('createPermissions'));
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowsResourceAccess(static::permissionConfig('editPermissions'));
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowsResourceAccess(static::permissionConfig('deletePermissions'));
    }

    public static function canDeleteAny(): bool
    {
        return static::allowsResourceAccess(static::permissionConfig('deletePermissions'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
