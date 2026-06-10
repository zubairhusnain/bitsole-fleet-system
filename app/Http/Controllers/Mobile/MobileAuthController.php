<?php

namespace App\Http\Controllers\Mobile;

use App\Helpers\Curl;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    use Curl;

    private function syncTraccarSession(Request $request, string $email): ?array
    {
        $data = 'email=' . Config::get('constants.Constants.adminEmail')
            . '&password=' . Config::get('constants.Constants.adminPassword');
        $response = static::curl('/api/session', 'POST', '', $data, [Config::get('constants.Constants.urlEncoded')]);
        $payload = json_decode($response->response ?? '');

        if ($response->responseCode != 200 || !$payload) {
            return null;
        }

        $cookie = $response->cookieData ?? '';
        if ($cookie !== '') {
            User::where('email', $email)->update(['traccarSession' => $cookie]);
        }

        return [
            'name' => $payload->name ?? null,
            'email' => $payload->email ?? $email,
            'tc_user_id' => $payload->id ?? null,
        ];
    }

    private function permissionsFor(User $user): array
    {
        $modules = array_keys(\App\Http\Middleware\ModulePermission::modules());
        return \App\Support\Permissions::effectiveMap($user, $modules);
    }

    private function authPayload(User $user, string $token): array
    {
        return [
            'user' => $user,
            'token' => $token,
            'permissions' => $this->permissionsFor($user),
        ];
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!$this->syncTraccarSession($request, $credentials['email'])) {
            return response()->json(['message' => 'Unable to authenticate with tracking server'], 500);
        }

        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json($this->authPayload($user, $token));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if (!$this->syncTraccarSession($request, $validated['email'])) {
            return response()->json(['message' => 'Unable to authenticate with tracking server'], 500);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_USER,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json($this->authPayload($user, $token), 201);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['user' => null, 'permissions' => []]);
        }

        return response()->json([
            'user' => $user,
            'permissions' => $this->permissionsFor($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['status' => 'logged_out']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fcm_token' => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        if (isset($validated['fcm_token'])) {
            $user->fcm_token = $validated['fcm_token'];
        }
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        $user->save();

        return response()->json(['user' => $user, 'message' => 'Profile updated']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 422);
    }
}
