<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TcEvent extends Model
{
    // Traccar table
    protected $connection = 'pgsql';
    protected $table = 'tc_events';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'servertime' => 'datetime',
        'eventtime' => 'datetime',
        'attributes' => 'array',
        'is_read' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(TcDevice::class, 'deviceid', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(TcNotification::class, 'type', 'type');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeWithEnabledNotifications($query)
    {
        return $query->where(function ($top) {
            $top->where(function ($main) {
                $main->whereHas('notifications', function ($q) {
                    $q->whereHas('devices', function ($dq) {
                        $dq->whereColumn('tc_devices.id', 'tc_events.deviceid');
                    });
                })->where(function ($q) {
                    $q->where(function ($q1) {
                        $q1->where('tc_events.type', '!=', 'maintenance')
                            ->whereHas('device', function ($dq) {
                                $dq->whereRaw("(CAST(attributes AS json)->>'alert_status' IS NULL OR CAST(attributes AS json)->>'alert_status' != 'disabled')");
                            });
                    })->orWhere(function ($q2) {
                        $q2->where('tc_events.type', 'maintenance')
                            ->whereHas('device', function ($dq) {
                                $dq->whereRaw("(CAST(attributes AS json)->>'maintenance_status' IS NULL OR CAST(attributes AS json)->>'maintenance_status' != 'disabled')");
                            });
                    });
                });
            })->orWhere('tc_events.type', 'frequentIgnition')
              ->orWhere('tc_events.type', 'driverChanged');
        });
    }
}
