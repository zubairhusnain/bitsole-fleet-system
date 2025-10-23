<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function scopeUsersFor(User $me)
    {
        if ($me->isAdmin()) {
            return User::query();
        }
        if ($me->isDistributor()) {
            return User::query()->where('distributor_id', $me->id);
        }
        // Fleet manager and normal users: only themselves
        return User::query()->where('id', $me->id);
    }

    private function roleLabel(int $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN => 'admin',
            User::ROLE_DISTRIBUTOR => 'distributor',
            User::ROLE_FLEET_MANAGER => 'fleet_manager',
            default => 'user',
        };
    }

    public function options(Request $request)
    {
        $me = $request->user();
        if (!$me) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $roles = [
            ['value' => User::ROLE_USER, 'label' => 'User'],
            ['value' => User::ROLE_FLEET_MANAGER, 'label' => 'Fleet Manager'],
            ['value' => User::ROLE_DISTRIBUTOR, 'label' => 'Distributor'],
            ['value' => User::ROLE_ADMIN, 'label' => 'Admin'],
        ];

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
            'distributors' => $distributors,
        ]);
    }

    public function index(Request $request)
    {
        $me = $request->user();
        if (!$me) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $q = trim((string)$request->query('q', ''));
        $builder = $this->scopeUsersFor($me);
        if ($q !== '') {
            $builder->where(function($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('id', $q);
            });
        }
        $users = $builder->orderByDesc('id')->limit(500)->get();

        $payload = $users->map(function(User $u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'roleLabel' => $this->roleLabel((int)$u->role),
                'distributor_id' => $u->distributor_id,
                'created_at' => optional($u->created_at)->toDateTimeString(),
            ];
        });

        return response()->json(['users' => $payload]);
    }

    public function show(Request $request, int $userId)
    {
        $me = $request->user();
        if (!$me) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $builder = $this->scopeUsersFor($me);
        $u = $builder->where('id', $userId)->first();
        if (!$u) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'roleLabel' => $this->roleLabel((int)$u->role),
            'distributor_id' => $u->distributor_id,
        ]);
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
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        // Auto-assign role and distributor based on creator
        if ($me->isAdmin()) {
            $role = User::ROLE_DISTRIBUTOR;
            $distributorId = null;
        } elseif ($me->isDistributor()) {
            $role = User::ROLE_FLEET_MANAGER;
            $distributorId = $me->id;
        } elseif ($me->isFleetManager()) {
            $role = User::ROLE_USER;
            $distributorId = $me->distributor_id ?? null;
        } else {
            $role = User::ROLE_USER;
            $distributorId = $me->distributor_id ?? null;
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role,
            'distributor_id' => $distributorId,
        ];

        $u = User::create($payload);
        return response()->json([
            'message' => 'User created',
            'user' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'roleLabel' => $this->roleLabel((int)$u->role),
                'distributor_id' => $u->distributor_id,
            ],
        ], 201);
    }

    public function update(Request $request, int $userId)
    {
        $me = $request->user();
        if (!$me) { return response()->json(['message' => 'Unauthorized'], 401); }

        $target = User::query()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }

        $isSelf = $me->id === $target->id;
        $isManager = $me->isAdmin() || $me->isDistributor();

        if (!$isSelf && !$isManager) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Managers can update all fields; self can update only name/email/password
        $rules = [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
        if ($isManager) {
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
        if ($isManager && $me->isDistributor()) {
            // Distributors cannot change distributor assignment away from themselves
            $data['distributor_id'] = $me->id;
        }

        $target->fill($data);
        $target->save();

        return response()->json([
            'message' => 'User updated',
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'roleLabel' => $this->roleLabel((int)$target->role),
                'distributor_id' => $target->distributor_id,
            ],
        ]);
    }

    public function destroy(Request $request, int $userId)
    {
        $me = $request->user();
        if (!$me) { return response()->json(['message' => 'Unauthorized'], 401); }
        if (!$me->isAdmin()) { return response()->json(['message' => 'Forbidden'], 403); }

        $target = User::query()->find($userId);
        if (!$target) { return response()->json(['message' => 'User not found'], 404); }

        $target->delete();
        return response()->json(['message' => 'User deleted']);
    }
}