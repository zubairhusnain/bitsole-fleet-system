<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcDriver extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'tc_drivers';
    public $timestamps = false;

    protected $guarded = [];
}