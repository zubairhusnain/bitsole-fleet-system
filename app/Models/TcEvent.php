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
        return $query->whereHas('notifications', function ($q) {
            $q->whereHas('devices', function ($dq) {
                   $dq->whereColumn('tc_devices.id', 'tc_events.deviceid');
              });
        });
    }
}
