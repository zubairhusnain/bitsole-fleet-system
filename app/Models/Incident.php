<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $table = 'incidents';
    protected $fillable = [
        'device_id',
        'vehicle_label',
        'type_model',
        'incident_start',
        'incident_end',
        'impact_time',
        'driver',
        'description',
        'remarks',
    ];
    protected $casts = [
        'incident_start' => 'datetime',
        'incident_end' => 'datetime',
        'impact_time' => 'datetime',
    ];
}
