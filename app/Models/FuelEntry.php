<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fuel_entries';

    protected $fillable = [
        'device_id',
        'fill_date',
        'quantity',
        'cost',
        'odometer',
        'notes',
        'fuel_type',
        'payment_type',
    ];

    protected $casts = [
        'fill_date' => 'datetime',
        'quantity' => 'decimal:2',
        'cost' => 'decimal:2',
        'odometer' => 'integer',
    ];

    /**
     * Get the device associated with the fuel entry.
     */
    public function device()
    {
        return $this->belongsTo(TcDevice::class, 'device_id', 'id');
    }
}
