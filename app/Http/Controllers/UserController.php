<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use App\Models\UserPermission;

class UserController extends Controller
{
    public function __construct()
    {
        // Global guard: only admin, distributor, or fleet manager can access UserController
        $this->middleware(function ($request, $next) {
            $me = $request->user();
            if (!$me) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            if (!$me->isAdmin() && !$me->isDistributor() && !$me->isFleetManager()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            return $next($request);
        });
    }
    private function scopeUsersFor(User $me)
    {
        if ($me->isAdmin()) {
            return User::query();
        }
        if ($me->isDistributor()) {
            return User::query()->where('distributor_id', $me->id);
        }
        // Fleet manager: see themselves and users they manage (manager_id = me)
        if ($me->isFleetManager()) {
            return User::query()->where(function($q) use ($me) {
                $q->where('id', $me->id)->orWhere('manager_id', $me->id);
            });
        }
        // Normal users: only themselves
        return User::query()->where('id', $me->id);
    }

    private function roleLabel(int $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN => 'admin',
            User::ROLE_DISTRIBUTOR => 'distributor',
            User::ROLE_FLEET_MANAGER => 'fleet manager',
            default => 'user',
        };
    }

    public function options(Request $request)
    {
        $me = $request->user();

        $roles = [
            ['value' => User::ROLE_USER, 'label' => 'User'],
            ['value' => User::ROLE_FLEET_MANAGER, 'label' => 'Fleet Manager'],
            ['value' => User::ROLE_DISTRIBUTOR, 'label' => 'Distributor'],
            ['value' => User::ROLE_ADMIN, 'label' => 'Admin'],
        ];

        if ($me->isAdmin() || $me->isDistributor()) {
            $roles = array_values(array_filter($roles, fn($r) => $r['value'] !== User::ROLE_USER));
        }

        // Modules list for permissions assignment (centralized in ModulePermission)
        $modulesConfig = \App\Http\Middleware\ModulePermission::modules();
        $modules = collect($modulesConfig)->map(function($label, $key) {
            return ['key' => $key, 'label' => $label];
        })->values();

        // Distributors list: admins see all distributors; distributors see only themselves
        $distributors = [];
        if ($me->isAdmin()) {
            $distributors = User::query()
                ->where('role', User::ROLE_DISTRIBUTOR)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values();
        } elseif ($me->isDistributor()) {
            $distributors = [ ['id' => $me->id, 'name' => $me->name, 'email' => $me->email] ];
        }

        return response()->json([
            'roles' => $roles,
            'modules' => $modules,
            'distributors' => $distributors,
        ]);
    }

    public function index(Request $request)
    {
        $me = $request->user();

        $q = trim((string)$request->query('q', ''));
        // Include blocked users if requested
        $withDeleted = $request->boolean('withDeleted');
        $builder = $withDeleted ? $this->scopeUsersFor($me)->withTrashed() : $this->scopeUsersFor($me);

        if ($me->isAdmin() || $me->isDistributor()) {
            $builder->where('role', '!=', User::ROLE_USER);
        }

        // Admin: exclude own account from the list
        if ($me->isAdmin()) {
            $builder->where('id', '!=', $me->id);
        }
        // Managers: show only users they manage (exclude self)
        if ($me->isFleetManager()) {
            $builder->where('manager_id', $me->id);
        }

        if ($q !== '') {
            $builder->where(function($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('id', $q);
            });
        }
        $users = $builder->orderByDesc('id')->limit(500)->get();

        // Build a mapping of distributor_id -> distributor name to avoid N+1 queries
        $distMap = collect();
        try {
            $distIds = $users->pluck('distributor_id')->filter()->unique()->values();
            if ($distIds->count() > 0) {
                $distMap = User::query()
                    ->whereIn('id', $distIds)
                    ->get(['id', 'name'])
                    ->keyBy('id');
            }
        } catch (\Throwable $e) {
            // If mapping fails, proceed without distributor names
            $distMap = collect();
        }

        // Store dist map in a simple global for closure capture without complex injection
        $GLOBALS['__distMap'] = [];
        if ($distMap instanceof \Illuminate\Support\Collection) {
            foreach ($distMap as $id => $row) { $GLOBALS['__distMap'][$id] = $row->name ?? null; }
        }
        $payload = $users->map(function(User $u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'roleLabel' => $this->roleLabel((int)$u->role),
                'role_label' => $u->role_label ?? $this->roleLabel((int)$u->role),
                'distributor_id' => $u->distributor_id,
                'distributorName' => isset($GLOBALS['__distMap']) && is_array($GLOBALS['__distMap']) && array_key_exists($u->distributor_id, $GLOBALS['__distMap'])
                    ? $GLOBALS['__distMap'][$u->distributor_id]
                    : null,
                'manager_id' => $u->manager_id,
                'created_at' => optional($u->created_at)->toDateTimeString(),
                'deletedAt' => optional($u->deleted_at)->toDateTimeString(),
                'blocked' => method_exists($u, 'trashed') ? $u->trashed() : false,
            ];
        });

        return response()->json(['users' => $payload]);
    }

    public function show(Request $request, $userId)
    {
        $me = $request->user();
        $builder = $this->scopeUsersFor($me);
        $u = $builder->where('id', $userId)->first();
        if (!$u) {
            return response()->json(['message' => 'User not found'], 404);
        }
        // Resolve distributor name
        $distName = null;
        try {
            if ($u->distributor_id) {
                $d = User::query()->find($u->distributor_id);
                $distName = $d ? $d->name : null;
            }
        } catch (\Throwable $e) {}
        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->role,
            'roleLabel' => $this->roleLabel((int)$u->role),
            'role_label' => $u->role_label ?? $this->roleLabel((int)$u->role),
            'distributor_id' => $u->distributor_id,
            'distributorName' => $distName,
            'manager_id' => $u->manager_id,
            'assigned_device_ids' => $u->devices()->pluck('devices.device_id')->toArray(),
        ]);
    }

    /**
     * List a user's module permissions.
     */
    public function permissions(Request $request, $userId)
    {
        $me = $request->user();
        $target = User::query()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }

        // Permission: admin can view any; distributor can view users/managers they own; manager can view users they manage; self can view
        $canView = false;
        if ($me->isAdmin()) { $canView = true; }
        elseif ($me->isDistributor()) { $canView = ((int)$target->distributor_id === (int)$me->id) || ((int)$target->id === (int)$me->id); }
        elseif ($me->isFleetManager()) { $canView = ((int)$target->manager_id === (int)$me->id) || ((int)$target->id === (int)$me->id); }
        else { $canView = ((int)$target->id === (int)$me->id); }
        if (!$canView) { return response()->json(['message' => 'Forbidden'], 403); }

        // Modules configuration (centralized)
        $modules = collect(\App\Http\Middleware\ModulePermission::modules());
        $rows = UserPermission::query()->where('user_id', $target->id)->get();
        $map = $rows->keyBy('module_key');
        $payload = $modules->map(function($label, $key) use ($map) {
            $row = $map->get($key);
            return [
                'key' => $key,
                'label' => $label,
                'can_read' => $row ? (bool)($row->can_read ?? $row->can_access) : false,
                'can_create' => $row ? (bool)($row->can_create ?? false) : false,
                'can_update' => $row ? (bool)($row->can_update ?? false) : false,
                'can_delete' => $row ? (bool)($row->can_delete ?? false) : false,
            ];
        })->values();
        return response()->json(['permissions' => $payload]);
    }

    /**
     * Update a user's module permissions: expects { permissions: [{ key, can_access }] }
     */
    public function updatePermissions(Request $request, $userId)
    {
        $me = $request->user();
        $target = User::query()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }
        // Assignability: admin -> any; distributor -> managers/users they own; manager -> users they manage
        $canAssign = false;
        if ($me->isAdmin()) { $canAssign = true; }
        elseif ($me->isDistributor()) { $canAssign = ((int)$target->distributor_id === (int)$me->id) && ((int)$target->role <= User::ROLE_FLEET_MANAGER); }
        elseif ($me->isFleetManager()) { $canAssign = ((int)$target->manager_id === (int)$me->id) && ((int)$target->role === User::ROLE_USER); }
        if (!$canAssign) { return response()->json(['message' => 'Forbidden'], 403); }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*.key' => ['required', 'string', 'max:80'],
            'permissions.*.can_read' => ['nullable', 'boolean'],
            'permissions.*.can_create' => ['nullable', 'boolean'],
            'permissions.*.can_update' => ['nullable', 'boolean'],
            'permissions.*.can_delete' => ['nullable', 'boolean'],
        ]);
        // Validate incoming keys against configured modules (centralized)
        $modules = array_keys(\App\Http\Middleware\ModulePermission::modules());
        $incoming = collect($data['permissions'] ?? [])
            ->filter(fn($p) => in_array($p['key'], $modules, true))
            ->keyBy('key');

        // Upsert rows and remove deselected keys
        foreach ($incoming as $key => $p) {
            UserPermission::query()->updateOrCreate(
                ['user_id' => $target->id, 'module_key' => $key],
                [
                    'can_access' => (bool)($p['can_read'] ?? false),
                    'can_read' => (bool)($p['can_read'] ?? false),
                    'can_create' => (bool)($p['can_create'] ?? false),
                    'can_update' => (bool)($p['can_update'] ?? false),
                    'can_delete' => (bool)($p['can_delete'] ?? false),
                ]
            );
        }
        // Remove keys not present (set to false)
        $existingKeys = UserPermission::query()->where('user_id', $target->id)->pluck('module_key')->all();
        $toRemove = array_diff($existingKeys, $incoming->keys()->all());
        if (count($toRemove) > 0) {
            UserPermission::query()->where('user_id', $target->id)->whereIn('module_key', $toRemove)->delete();
        }

        return response()->json(['message' => 'Permissions updated']);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        if (!$me) { return response()->json(['message' => 'Unauthorized'], 401); }
        // Admins, distributors, and fleet managers can create users
        if (!$me->isAdmin() && !$me->isDistributor() && !$me->isFleetManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        // Auto-assign role and distributor based on creator
        if ($me->isAdmin()) {
            $role = User::ROLE_DISTRIBUTOR;
            $distributorId = null;
            $managerId = null;
        } elseif ($me->isDistributor()) {
            $role = User::ROLE_FLEET_MANAGER;
            $distributorId = $me->id;
            $managerId = null;
        } elseif ($me->isFleetManager()) {
            $role = User::ROLE_USER;
            $distributorId = $me->distributor_id ?? null;
            $managerId = $me->id; // track creator as manager for normal users
        } else {
            $role = User::ROLE_USER;
            $distributorId = $me->distributor_id ?? null;
            $managerId = null;
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $request->input('phone'),
            'password' => Hash::make($data['password']),
            'role' => $role,
            'distributor_id' => $distributorId,
            'manager_id' => $managerId,
        ];

        $u = User::create($payload);

        // Vehicle Assignment (Fleet Manager only)
        if ($request->has('device_ids') && $me->isFleetManager() && $role === User::ROLE_USER) {
            $deviceIds = $request->input('device_ids');
            if (is_array($deviceIds)) {
                $validInternalIds = \App\Models\Devices::accessibleByUser($me)
                    ->whereIn('device_id', $deviceIds)
                    ->pluck('id');
                $u->devices()->sync($validInternalIds);
            }
        }

        return response()->json([
            'message' => 'User created',
            'user' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'roleLabel' => $this->roleLabel((int)$u->role),
                'role_label' => $u->role_label ?? $this->roleLabel((int)$u->role),
                'distributor_id' => $u->distributor_id,
            ],
        ], 201);
    }

    public function update(Request $request, $userId)
    {
        $me = $request->user();
        if (!$me) { return response()->json(['message' => 'Unauthorized'], 401); }

        $target = User::query()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }

        $isSelf = $me->id === $target->id;
        $isAdminOrDistributor = $me->isAdmin() || $me->isDistributor();
        $isFleetManager = $me->isFleetManager();

        // Permission matrix:
        // - Self: can update own basic fields
        // - Admin/Distributor: can update any user, including role/distributor
        // - Fleet manager: can update basic fields for normal users they manage
        $canEditOther = false;
        if ($isAdminOrDistributor) {
            $canEditOther = true;
        } elseif ($isFleetManager) {
            $canEditOther = ((int)$target->manager_id === (int)$me->id) && ((int)$target->role === User::ROLE_USER);
        }
        if (!$isSelf && !$canEditOther) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Validation rules: default basic fields; expand only for admin/distributor
        $rules = [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($target->id)],
            'phone' => ['sometimes', 'string', 'max:30'],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
        if ($isAdminOrDistributor) {
            $rules['role'] = ['sometimes', 'integer', Rule::in([User::ROLE_USER, User::ROLE_FLEET_MANAGER, User::ROLE_DISTRIBUTOR, User::ROLE_ADMIN])];
            $rules['distributor_id'] = ['nullable', 'integer'];
        }

        $data = $request->validate($rules);

        if (isset($data['role']) && !$me->isAdmin() && (int)$data['role'] === User::ROLE_ADMIN) {
            return response()->json(['message' => 'Forbidden: cannot assign admin role'], 403);
        }
        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
        }
        if ($isAdminOrDistributor && $me->isDistributor()) {
            // Distributors cannot change distributor assignment away from themselves
            $data['distributor_id'] = $me->id;
        }

        $target->fill($data);
        $target->save();

        // Vehicle Assignment (Fleet Manager only)
        if ($request->has('device_ids') && $me->isFleetManager() && (int)$target->role === User::ROLE_USER) {
            $deviceIds = $request->input('device_ids');
            if (is_array($deviceIds)) {
                $validInternalIds = \App\Models\Devices::accessibleByUser($me)
                    ->whereIn('device_id', $deviceIds)
                    ->pluck('id');
                $target->devices()->sync($validInternalIds);
            }
        }

        return response()->json([
            'message' => 'User updated',
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'role' => $target->role,
                'roleLabel' => $this->roleLabel((int)$target->role),
                'role_label' => $target->role_label ?? $this->roleLabel((int)$target->role),
                'distributor_id' => $target->distributor_id,
            ],
        ]);
    }

    public function destroy(Request $request, $userId)
    {
        $me = $request->user();
        if (!$me) { return response()->json(['message' => 'Unauthorized'], 401); }
        // Role flags
        $isAdmin = $me->isAdmin();
        $isDistributor = $me->isDistributor();
        $isFleetManager = $me->isFleetManager();

        // Soft delete by default (block). Use force=1 (or hard=1) to permanently delete locally.
        $force = $request->boolean('force') || $request->boolean('hard');

        if (!$force) {
            // SOFT DELETE (block)
            $target = User::query()->find($userId);
            if (!$target) { return response()->json(['message' => 'User not found'], 404); }
            if (method_exists($target, 'trashed') && $target->trashed()) {
                return response()->json(['message' => 'User already blocked'], 200);
            }
            // Permission: admin can block anyone; distributor can block users they own (users/managers);
            // fleet manager can block only normal users they manage
            $canBlock = false;
            if ($isAdmin) {
                $canBlock = true;
            } elseif ($isDistributor) {
                $canBlock = ((int)$target->distributor_id === (int)$me->id) && ((int)$target->role <= User::ROLE_FLEET_MANAGER);
            } elseif ($isFleetManager) {
                $canBlock = ((int)$target->manager_id === (int)$me->id) && ((int)$target->role === User::ROLE_USER);
            }
            if (!$canBlock) { return response()->json(['message' => 'Forbidden'], 403); }
            try {
                $target->delete();
                // Cascade block: if distributor gets blocked, block all users under this distributor
                if ($isAdmin && (int)$target->role === User::ROLE_DISTRIBUTOR) {
                    $children = User::query()
                        ->where('distributor_id', $target->id)
                        ->whereNull('deleted_at')
                        ->get();
                    foreach ($children as $child) {
                    try {
                        if (!($child instanceof User)) {
                            $child = User::query()->find($child->id);
                        }
                        if ($child) {
                            $child->delete();
                        }
                    } catch (\Throwable $e) { /* skip child on error */ }
                }
                }
            }
            catch (\Throwable $e) {
                return response()->json(['message' => 'Failed to block user', 'error' => $e->getMessage()], 500);
            }
            return response()->json(['message' => 'User blocked'], 200);
        }

        // HARD DELETE: permanently remove local record (no remote dependencies for users)
        if (!$isAdmin) { return response()->json(['message' => 'Forbidden'], 403); }
        $target = User::withTrashed()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }
        try { $target->forceDelete(); }
        catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to permanently delete user', 'error' => $e->getMessage()], 500);
        }
        return response()->json(['message' => 'User deleted'], 200);
    }

    /**
     * Restore (activate) a soft-deleted user.
     */
    public function restore(Request $request, $userId)
    {
        $me = $request->user();
        if (!$me) { return response()->json(['message' => 'Unauthorized'], 401); }
        if (!$me->canDelete('users')) { return response()->json(['message' => 'Forbidden'], 403); }
        // Role flags
        $isAdmin = $me->isAdmin();
        $isDistributor = $me->isDistributor();
        $isFleetManager = $me->isFleetManager();

        $target = User::withTrashed()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }
        if (method_exists($target, 'trashed') && !$target->trashed()) {
            return response()->json(['message' => 'User already active'], 200);
        }
        // Permission: admin can restore anyone; distributor can restore their users/managers; fleet manager restores only their users
        $canRestore = false;
        if ($isAdmin) {
            $canRestore = true;
        } elseif ($isDistributor) {
            $canRestore = ((int)$target->distributor_id === (int)$me->id) && ((int)$target->role <= User::ROLE_FLEET_MANAGER);
        } elseif ($isFleetManager) {
            $canRestore = ((int)$target->manager_id === (int)$me->id) && ((int)$target->role === User::ROLE_USER);
        }
        if (!$canRestore) { return response()->json(['message' => 'Forbidden'], 403); }
        try {
            $target->restore();
            // Cascade restore: if distributor is restored, restore all users under this distributor
            if ($isAdmin && (int)$target->role === User::ROLE_DISTRIBUTOR) {
                $children = User::withTrashed()
                    ->where('distributor_id', $target->id)
                    ->whereNotNull('deleted_at')
                    ->get();
                foreach ($children as $child) {
                    try {
                        if (!($child instanceof User)) {
                            $child = User::withTrashed()->find($child->id);
                        }
                        if ($child) {
                            $child->restore();
                        }
                    } catch (\Throwable $e) { /* skip child on error */ }
                }
            }
        }
        catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to activate user', 'error' => $e->getMessage()], 500);
        }
        return response()->json(['message' => 'User activated'], 200);
    }
}
