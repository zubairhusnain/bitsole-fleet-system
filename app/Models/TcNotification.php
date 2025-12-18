<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcNotification extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'tc_notifications';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'always' => 'boolean',
        'attributes' => 'array',
    ];

    public function events()
    {
        return $this->hasMany(TcEvent::class, 'type', 'type');
    }

    public function devices()
    {
        return $this->belongsToMany(TcDevice::class, 'tc_device_notification', 'notificationid', 'deviceid');
    }
}

