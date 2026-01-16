<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    use HasFactory;

    protected $table = 'vehicle_models';

    protected $fillable = [
        'modelname',
        'odmeter_ioid',
        'fuel_ioid',
        'attributes',
        'created_by',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];
}

