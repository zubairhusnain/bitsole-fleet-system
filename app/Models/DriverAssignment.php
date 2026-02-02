<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
    ];

    public function driver()
    {
        return $this->belongsTo(Drivers::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(TcDevice::class, 'vehicle_id', 'id');
    }
}
