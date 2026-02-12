<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LogSystemActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    protected static $isLogged = false;

    public function handle(Request $request, Closure $next): Response
    {
        // Skip GET/HEAD/OPTIONS and common background/internal paths
        $method = $request->method();
        $path = $request->path();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS']) ||
            str_contains($path, 'status') ||
            str_contains($path, 'me') ||
            str_contains($path, 'broadcasting/auth') ||
            str_contains($path, 'csrf-token')) {
            return $next($request);
        }

        // Check if logging is enabled in config
        if (!config('app.system_log_enabled') || static::$isLogged) {
            return $next($request);
        }

        static::$isLogged = true;

        // Capture user before request (crucial for logout)
        $userBefore = Auth::user();

        // Pre-capture old data for UPDATE/DELETE
        $oldData = null;
        $resolvedModelInstance = null;
        $modelNameFromRoute = $this->guessModelFromRoute($request);

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $params = $request->route()->parameters();
            foreach ($params as $key => $val) {
                $instance = $this->resolveModelFromParameter($request, $key, $val);
                if ($instance) {
                    $oldData = $instance->toArray();
                    $resolvedModelInstance = $instance;
                    break;
                }
            }
        }

        $response = $next($request);

        // Only log successful requests (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $userAfter = Auth::user();
            $userToLog = $userBefore ?? $userAfter;

            if ($userToLog) {
                $this->logActivity($request, $method, $oldData, $modelNameFromRoute, $userToLog, $resolvedModelInstance);
            }
        }

        return $response;
    }

    protected function resolveModelFromParameter(Request $request, $key, $id)
    {
        $map = [
            'userId' => \App\Models\User::class,
            'user' => \App\Models\User::class,
            'deviceId' => \App\Models\Devices::class,
            'zoneId' => \App\Models\TcGeofence::class,
            'driverId' => \App\Models\Drivers::class,
            'id' => $this->guessModelFromRoute($request),
        ];

        if (isset($map[$key]) && $map[$key]) {
            try {
                return $map[$key]::find($id);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    protected function guessModelFromRoute(Request $request)
    {
        $segments = $request->segments();
        // web, users, 1 -> users
        // web, settings, vehicle-models, 1 -> vehicle-models
        $module = $segments[1] ?? null;
        if ($module === 'settings' || $module === 'admin') {
            $module = $segments[2] ?? $module;
        }

        if (!$module) return null;

        return match ($module) {
            'users' => \App\Models\User::class,
            'vehicles' => \App\Models\Devices::class,
            'zones' => \App\Models\TcGeofence::class,
            'drivers' => \App\Models\Drivers::class,
            'fuel' => \App\Models\FuelEntry::class,
            'vehicle-models' => \App\Models\VehicleModel::class,
            default => null,
        };
    }

    protected function logActivity(Request $request, $method, $oldData, $modelNameFromRoute, $user, $resolvedModelInstance = null)
    {
        // Determine Action
        $action = match ($method) {
            'POST' => 'CREATE',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => $method,
        };

        // Determine Module
        $module = $modelNameFromRoute;
        if (is_string($module) && class_exists($module)) {
            $module = class_basename($module);
        }

        if (!$module) {
            $segments = $request->segments();
            if (isset($segments[1])) {
                $module = ucfirst($segments[1]);
                if (($module === 'Settings' || $module === 'Admin' || $module === 'Vehicles' || $module === 'Drivers') && isset($segments[2])) {
                    $module = ucfirst($segments[2]);
                }
            } else {
                $module = 'System';
            }
        }

        $path = $request->path();
        if ($module === 'Auth' || $module === 'Login') {
            if (str_contains($path, 'login')) {
                $module = 'Login';
                $action = 'LOGIN';
            } elseif (str_contains($path, 'logout')) {
                $module = 'Logout';
                $action = 'LOGOUT';
            } elseif (str_contains($path, 'impersonate')) {
                $module = 'Impersonate';
            } else {
                $module = 'Login';
                $action = 'LOGIN';
            }
        }

        // Prevent duplicate Login/Logout/Impersonate logs within 5 seconds for same user
        if (in_array($module, ['Login', 'Logout', 'Impersonate'])) {
            $cacheKey = "log_cooldown_{$user->id}_{$module}";
            try {
                if (Cache::has($cacheKey)) {
                    return;
                }
                Cache::put($cacheKey, true, 5);
            } catch (\Throwable $e) {
                // If cache is broken (e.g. permissions), ignore and continue to avoid crashing the app
            }
        }

        // Determine New Data
        $newData = null;
        if ($action === 'CREATE') {
            $newData = $request->except(['password', 'password_confirmation', '_token']);
        } elseif ($action === 'UPDATE') {
            if ($resolvedModelInstance) {
                // Refresh to get the updated state from DB
                try {
                    $resolvedModelInstance->refresh();
                    $newData = $resolvedModelInstance->toArray();
                } catch (\Throwable $e) {
                    $newData = $request->except(['password', 'password_confirmation', '_token']);
                }
            } else {
                $newData = $request->except(['password', 'password_confirmation', '_token']);
            }
        }

        // Description
        $description = "$action record in $module module";

        SystemActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => $module,
            'request_path' => $request->path(),
            'description' => $description,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => $request->ip(),
        ]);
    }
}
