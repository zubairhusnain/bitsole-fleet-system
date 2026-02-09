<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Helpers\Curl;

class AuthController extends Controller
{
    use Curl;
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Pre-validate against tracking server before local login
        $data = 'email=' . Config::get('constants.Constants.adminEmail') . '&password=' . Config::get('constants.Constants.adminPassword');
        $response = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);
        $payload = json_decode($response->response ?? '');
        if ($response->responseCode == 200 && $payload) {
            $cookie = $request->session()->get('cookie', '');

            // Persist session data and cookie
            Session::put([
                'name' => $payload->name ?? null,
                'email' => $payload->email ?? $credentials['email'],
                'tc_user_id' => $payload->id ?? null,
                'admin' => $payload->administrator ?? false,
                'readonly' => $payload->readonly ?? false,
                'cookieData' => $cookie,
                'deviceReadonly' => $payload->deviceReadonly ?? false,
            ]);

            if ($cookie !== '') {
                User::where('email', $credentials['email'])->update(['traccarSession' => $cookie]);
            }
        } else {
            // Block login when tracking credentials fail
            $msg = 'Unable to authenticate with tracking server';
            if ($response->responseCode == 401) {
                $msg = 'Invalid Email Or Password';
            } elseif ($response->responseCode == 400) {
                $response_error = substr($response->response ?? '', 0, 19);
                $msg = $response_error === 'Account is disabled' ? 'User Blocked' : 'Server Not Responding';
            }
            return response()->json(['message' => $msg], $response->responseCode == 401 ? 422 : 500);
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 422);
        }

        $request->session()->regenerate();

        // Seed session with user permission keys to avoid per-request DB lookups
        try {
            $u = Auth::user();
            if ($u) {
                $keys = \App\Models\UserPermission::query()
                    ->where('user_id', $u->id)
                    ->pluck('module_key')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                session(['user_module_keys' => $keys]);
            }
        } catch (\Throwable $e) { /* ignore session seed errors */ }

        return response()->json(['user' => Auth::user()]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['user' => null, 'permissions' => [], 'impersonator' => null]);
        }
        // Ensure session holds module keys for middleware performance
        try {
            if (!session()->has('user_module_keys')) {
                $keys = \App\Models\UserPermission::query()
                    ->where('user_id', $user->id)
                    ->pluck('module_key')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                session(['user_module_keys' => $keys]);
            }
        } catch (\Throwable $e) { /* ignore seeding errors */ }
        $modules = array_keys(\App\Http\Middleware\ModulePermission::modules());
        $perms = \App\Support\Permissions::effectiveMap($user, $modules);

        $impersonator = null;
        try {
            $stack = session('impersonator_stack');
            if (!$stack && session()->has('impersonator_id')) {
                $legacy = session('impersonator_id');
                if ($legacy) {
                    $stack = [$legacy];
                    session(['impersonator_stack' => $stack]);
                }
                session()->forget('impersonator_id');
            }
            if (is_array($stack) && !empty($stack)) {
                $impersonatorId = end($stack);
                if ($impersonatorId) {
                    $impersonator = User::query()->find($impersonatorId);
                }
            }
        } catch (\Throwable $e) {
        }

        return response()->json(['user' => $user, 'permissions' => $perms, 'impersonator' => $impersonator]);
    }

    public function logout(Request $request)
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        try { session()->forget('user_module_keys'); } catch (\Throwable $e) {}
        return response()->json(['status' => 'logged_out']);
    }

    public function impersonate(Request $request, $userId)
    {
        $me = $request->user();
        if (!$me) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userIdInt = (int)$userId;
        if ($userIdInt <= 0) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $target = User::query()->find($userIdInt);
        if (!$target) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $can = false;
        if ($me->isAdmin() && (int)$target->role === User::ROLE_DISTRIBUTOR) {
            $can = true;
        } elseif ($me->isDistributor()
            && (int)$target->role === User::ROLE_FLEET_MANAGER
            && (int)$target->distributor_id === (int)$me->id) {
            $can = true;
        }

        if (!$can) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Authenticate with tracking server (mirroring login logic)
        $data = 'email=' . Config::get('constants.Constants.adminEmail') . '&password=' . Config::get('constants.Constants.adminPassword');
        $response = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);
        $payload = json_decode($response->response ?? '');

        if ($response->responseCode == 200 && $payload) {
            $cookie = $request->session()->get('cookie', '');

            // Persist session data and cookie
            Session::put([
                'name' => $payload->name ?? null,
                'email' => $payload->email ?? $target->email,
                'tc_user_id' => $payload->id ?? null,
                'admin' => $payload->administrator ?? false,
                'readonly' => $payload->readonly ?? false,
                'cookieData' => $cookie,
                'deviceReadonly' => $payload->deviceReadonly ?? false,
            ]);

            if ($cookie !== '') {
                $target->update(['traccarSession' => $cookie]);
            }
        } else {
            // Block impersonation when tracking credentials fail
            $msg = 'Unable to authenticate with tracking server';
            if ($response->responseCode == 401) {
                $msg = 'Invalid Email Or Password';
            } elseif ($response->responseCode == 400) {
                $response_error = substr($response->response ?? '', 0, 19);
                $msg = $response_error === 'Account is disabled' ? 'User Blocked' : 'Server Not Responding';
            }
            return response()->json(['message' => $msg], $response->responseCode == 401 ? 422 : 500);
        }

        $stack = session('impersonator_stack', []);
        if (!is_array($stack)) {
            $stack = [];
        }
        $stack[] = $me->id;
        session(['impersonator_stack' => $stack]);
        Auth::login($target);
        $request->session()->regenerate();

        try {
            $keys = \App\Models\UserPermission::query()
                ->where('user_id', $target->id)
                ->pluck('module_key')
                ->filter()
                ->unique()
                ->values()
                ->all();
            session(['user_module_keys' => $keys]);
        } catch (\Throwable $e) {
        }

        return response()->json(['user' => Auth::user()]);
    }

    public function stopImpersonate(Request $request)
    {
        try {
            $stack = session('impersonator_stack', []);
            if ((!is_array($stack) || empty($stack)) && session()->has('impersonator_id')) {
                $legacy = session('impersonator_id');
                if ($legacy) {
                    $stack = [$legacy];
                    session(['impersonator_stack' => $stack]);
                }
                session()->forget('impersonator_id');
            }

            if (!is_array($stack) || empty($stack)) {
                return response()->json(['message' => 'Not impersonating'], 400);
            }

            $impersonatorId = array_pop($stack);

            if (empty($stack)) {
                session()->forget('impersonator_stack');
            } else {
                session(['impersonator_stack' => $stack]);
            }

            $original = $impersonatorId ? User::query()->find($impersonatorId) : null;
            if (!$original) {
                Auth::guard()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                try {
                    session()->forget('user_module_keys');
                } catch (\Throwable $e) {
                }
                try {
                    session()->forget('impersonator_stack');
                } catch (\Throwable $e) {
                }
                return response()->json(['status' => 'logged_out']);
            }

            Auth::login($original);
            $request->session()->regenerate();

            try {
                $keys = \App\Models\UserPermission::query()
                    ->where('user_id', $original->id)
                    ->pluck('module_key')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                session(['user_module_keys' => $keys]);
            } catch (\Throwable $e) {
            }

            return response()->json(['user' => Auth::user()]);
        } catch (\Throwable $e) {
            Log::error('stopImpersonate failed', [
                'error' => $e->getMessage(),
            ]);

            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            try {
                session()->forget('user_module_keys');
            } catch (\Throwable $e2) {
            }
            try {
                session()->forget('impersonator_stack');
            } catch (\Throwable $e2) {
            }

            return response()->json(['status' => 'logged_out']);
        }
    }

}
