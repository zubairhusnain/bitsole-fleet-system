<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devices extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'devices';

    protected $fillable = [
        'device_id',
        'user_id',
        'distributor_id',
        'manager_id',
    ];

    protected $casts = [
        'device_id' => 'integer',
        'user_id' => 'integer',
        'distributor_id' => 'integer',
        'manager_id' => 'integer',
    ];

    public function tcDevice(): BelongsTo
    {
        return $this->belongsTo(TcDevice::class, 'device_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'device_user', 'device_id', 'user_id');
    }
}
