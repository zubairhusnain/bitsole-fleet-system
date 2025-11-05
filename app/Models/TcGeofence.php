<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcGeofence extends Model
{
    // Traccar database connection
    protected $connection = 'pgsql';

    // Traccar geofences table
    protected $table = 'tc_geofences';

    // Traccar tables typically have no Laravel timestamps
    public $timestamps = false;

    protected $guarded = [];
}