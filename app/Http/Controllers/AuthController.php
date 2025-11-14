<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
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
            return response()->json(['user' => null, 'permissions' => []]);
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
        $modulesConfig = config('modules') ?? [];
        if (empty($modulesConfig)) {
            $modulesConfig = [
                'live-tracking' => 'Live Tracking',
                'drivers' => 'Driver Management',
                'vehicles' => 'Vehicle Management',
                'vehicles.maintenance' => 'Vehicle Maintenance',
                'vehicles.overview' => 'Vehicle Overview',
                'zones' => 'Zone Management',
                'users' => 'User Management',
                'users.permissions' => 'User Permission',
            ];
        }
        $modules = array_keys($modulesConfig);
        $perms = \App\Support\Permissions::effectiveMap($user, $modules);
        return response()->json(['user' => $user, 'permissions' => $perms]);
    }

    public function logout(Request $request)
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        try { session()->forget('user_module_keys'); } catch (\Throwable $e) {}
        return response()->json(['status' => 'logged_out']);
    }

}
