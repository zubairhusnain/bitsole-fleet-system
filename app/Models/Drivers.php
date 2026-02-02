<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drivers extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id',
        'distributor_id',
        'driver_id',
        'device_id',
        'is_client_driver',
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'device_id' => 'integer',
        'user_id' => 'integer',
        'distributor_id' => 'integer',
        'is_client_driver' => 'boolean',
    ];

    public function tcDriver()
    {
        return $this->belongsTo(TcDriver::class, 'driver_id', 'id');
    }

    public function tcDevice()
    {
        return $this->belongsTo(TcDevice::class, 'device_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(DriverAssignment::class, 'driver_id');
    }
}
