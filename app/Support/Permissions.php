<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserPermission;

class Permissions
{
    public static function check(User $user, string $moduleKey, string $action): bool
    {
        if ($user->isAdmin() || $user->isDistributor()) return true;
        $row = UserPermission::query()->where('user_id', $user->id)->where('module_key', $moduleKey)->first();
        if ($row) {
            return match ($action) {
                'read' => (bool)($row->can_read ?? $row->can_access ?? false),
                'create' => (bool)($row->can_create ?? false),
                'update' => (bool)($row->can_update ?? false),
                'delete' => (bool)($row->can_delete ?? false),
                default => false,
            };
        }
        return static::fallbackCheck($user, $moduleKey, $action);
    }

    public static function effectiveMap(User $user, array $modules): array
    {
        $rows = UserPermission::query()->where('user_id', $user->id)->get()->keyBy('module_key');
        $map = [];
        foreach ($modules as $key) {
            $row = $rows->get($key);
            if ($user->isAdmin() || $user->isDistributor()) {
                $map[$key] = ['read' => true, 'create' => true, 'update' => true, 'delete' => true];
                continue;
            }
            if ($row) {
                $map[$key] = [
                    'read' => (bool)($row->can_read ?? $row->can_access ?? false),
                    'create' => (bool)($row->can_create ?? false),
                    'update' => (bool)($row->can_update ?? false),
                    'delete' => (bool)($row->can_delete ?? false),
                ];
            } else {
                $map[$key] = [
                    'read' => static::fallbackCheck($user, $key, 'read'),
                    'create' => static::fallbackCheck($user, $key, 'create'),
                    'update' => static::fallbackCheck($user, $key, 'update'),
                    'delete' => static::fallbackCheck($user, $key, 'delete'),
                ];
            }
        }
        return $map;
    }

    protected static function fallbackCheck(User $user, string $moduleKey, string $action): bool
    {
        return false;
    }
}
