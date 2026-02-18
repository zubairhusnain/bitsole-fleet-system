<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemActivityLog;
use Illuminate\Database\Eloquent\Model;

class LogSystemActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Capture Old Data (before processing)
        // Only for PUT/PATCH/DELETE
        if (!config('app.system_log_enabled')) {
            return $next($request);
        }
        $method = $request->method();
        $oldData = null;
        $modelName = null;

        // Skip GET/HEAD/OPTIONS
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Try to find a bound model in route parameters
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $route = $request->route();
            if ($route) {
                foreach ($route->parameters() as $key => $value) {
                    if ($value instanceof Model) {
                        $modelName = class_basename($value);
                        $oldData = $value->toArray();
                        break; // Assume the first model is the primary resource
                    }
                }
            }
        }

        // 2. Process Request
        $response = $next($request);

        // 3. Log (After processing)
        // We do this after to ensure the request was successful (2xx)
        if ($response->isSuccessful()) {
            $this->logActivity($request, $method, $oldData, $modelName);
        }

        return $response;
    }

    protected function logActivity(Request $request, $method, $oldData, $modelNameFromRoute)
    {
        $user = Auth::user();

        // If no user, we might skip or log as Guest (user requirements: fleetmanager/distributor/viewer)
        // For now, only log if user is authenticated.
        if (!$user) {
            return;
        }

        // Determine Action
        $action = match ($method) {
            'POST' => 'CREATE',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => $method,
        };

        $segments = $request->segments();
        $module = 'System';
        if (isset($segments[1])) {
            $slug = $segments[1];
            $map = [
                'fuel' => 'Fuel Management',
                'vehicles' => 'Vehicle Management',
                'drivers' => 'Driver Management',
                'zones' => 'Zone Management',
                'monitoring' => 'Monitoring',
                'maintenance' => 'Maintenance',
                'reports' => 'Reports',
                'alerts' => 'Alerts',
                'users' => 'User Management',
                'system-activity-logs' => 'System Activity Logs',
            ];
            if (isset($map[$slug])) {
                $module = $map[$slug];
            } else {
                $module = ucfirst($slug);
            }
        }

        // Determine New Data
        $newData = null;
        if ($action === 'CREATE' || $action === 'UPDATE') {
            $newData = $request->except(['password', 'password_confirmation', '_token']);
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
