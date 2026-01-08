<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcGroup extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'tc_groups';
    public $timestamps = false;
    protected $guarded = [];
}
