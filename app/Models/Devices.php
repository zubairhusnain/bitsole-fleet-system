<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devices extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'devices';

    protected $fillable = [
        'device_id',
        'user_id',
        'distributor_id',
        'manager_id',
    ];

    protected $casts = [
        'device_id' => 'integer',
        'user_id' => 'integer',
        'distributor_id' => 'integer',
        'manager_id' => 'integer',
    ];

    public function tcDevice(): BelongsTo
    {
        return $this->belongsTo(TcDevice::class, 'device_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'device_user', 'device_id', 'user_id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributor_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function events()
    {
        return $this->hasMany(TcEvent::class, 'deviceid', 'device_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(TcNotification::class, 'tc_device_notification', 'deviceid', 'notificationid')
            ->using(TcDeviceNotification::class);
    }

    /**
     * Scope the query to only include devices accessible by the given user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccessibleByUser($query, $user)
    {
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        if ($role === User::ROLE_ADMIN) {
            return $query;
        }

        if ($role === User::ROLE_DISTRIBUTOR) {
            return $query->where('distributor_id', $user->id);
        }

        // For Fleet Manager and User
        $distId = $user->distributor_id ?? $user->id;
        $query->where('distributor_id', $distId);

        if ($role === User::ROLE_FLEET_MANAGER) {
            return $query->where('manager_id', $user->id);
        }

        // Default User
        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
