<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcDeviceNotification extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'tc_device_notification';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['deviceid', 'notificationid'];
}
