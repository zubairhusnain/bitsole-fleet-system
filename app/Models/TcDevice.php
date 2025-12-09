<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TcDevice extends Model
{
    // Tracking server tables typically don't have Laravel timestamps
    public $timestamps = false;

    protected $guarded = [];

    // Position relation via positionid
    public function position(): BelongsTo
    {
        return $this->belongsTo(TcPosition::class, 'positionid', 'id');
    }
}
