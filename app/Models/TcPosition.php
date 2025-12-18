<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcPosition extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'tc_positions';

    public $timestamps = false;

    protected $guarded = [];
}
