<?php

namespace App\Filament\Concerns;

/**
 * Ties Filament resource auth checks to spatie/laravel-permission permission names
 * matching the pattern "<ability>_<resource>" (e.g. "view_any_vehicle").
 *
 * Implementing resources must expose `getPermissionPrefix()` returning the bare
 * resource name (e.g. "vehicle"). super-admin is handled globally via Gate::before.
 */
trait AuthorizesViaSpatie
{
    abstract public static function getPermissionPrefix(): string;

    protected static function currentUserCan(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($ability);
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCan('view_any_' . static::getPermissionPrefix());
    }

    public static function canView($record): bool
    {
        return static::currentUserCan('view_' . static::getPermissionPrefix());
    }

    public static function canCreate(): bool
    {
        return static::currentUserCan('create_' . static::getPermissionPrefix());
    }

    public static function canEdit($record): bool
    {
        return static::currentUserCan('update_' . static::getPermissionPrefix());
    }

    public static function canDelete($record): bool
    {
        return static::currentUserCan('delete_' . static::getPermissionPrefix());
    }

    public static function canDeleteAny(): bool
    {
        return static::currentUserCan('delete_' . static::getPermissionPrefix());
    }

    public static function canRestore($record): bool
    {
        return static::currentUserCan('restore_' . static::getPermissionPrefix());
    }

    public static function canForceDelete($record): bool
    {
        return static::currentUserCan('force_delete_' . static::getPermissionPrefix());
    }
}
