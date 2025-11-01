<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zones extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'zones';

    protected $fillable = [
        'user_id',
        'distributor_id',
        'geofence_id',
        'name',
        'description',
        'status',
        'speed',
        'coordinates',
        'radius',
        'polygon',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'distributor_id' => 'integer',
        'geofence_id' => 'integer',
        'speed' => 'float',
        'radius' => 'float',
        'coordinates' => 'array',
        'polygon' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}