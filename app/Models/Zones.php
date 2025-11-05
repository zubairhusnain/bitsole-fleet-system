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
    ];

    protected $casts = [
        'user_id' => 'integer',
        'distributor_id' => 'integer',
        'geofence_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}