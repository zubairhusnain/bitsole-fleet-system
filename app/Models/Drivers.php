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
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'device_id' => 'integer',
        'user_id' => 'integer',
        'distributor_id' => 'integer',
    ];

    public function tcDriver()
    {
        return $this->belongsTo(TcDriver::class, 'driver_id', 'id');
    }

    public function tcDevice()
    {
        return $this->belongsTo(TcDevice::class, 'device_id', 'id');
    }
}
