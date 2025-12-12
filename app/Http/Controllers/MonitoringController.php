<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    /**
     * List vehicles for monitoring with detailed attributes.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Apply role-based access control
        if ($request->boolean('mine')) {
            $query = Devices::accessibleByUser($user);
            $query->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } else {
            $query = Devices::accessibleByUser($user);
        }

        // Eager load tcDevice and its current position
        // We include soft-deleted devices if they are still relevant for monitoring history,
        // but typically monitoring focuses on active devices. 
        // VehicleController includes withTrashed(), so we'll keep it for consistency.
        $query->withTrashed()->with(['tcDevice.position']);

        // Pagination or fetch all
        $perPage = $request->input('per_page', 25);
        
        // If per_page is -1 or very large, we might want to return all, but paginate is safer.
        // The frontend requests per_page=500 in fetchVehicles.
        $devices = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($devices);
    }
}
