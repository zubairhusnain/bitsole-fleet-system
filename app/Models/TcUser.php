<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TcUser extends Model
{
    // Use the default PostgreSQL connection
    protected $connection = 'pgsql';

    // Public schema: rely on search_path to resolve schema
    protected $table = 'tc_users';

    // Primary key usually 'id'; adjust if different
    protected $primaryKey = 'id';

    // Many external tables don't have Laravel timestamps
    public $timestamps = false;

    // Allow mass assignment if needed (adjust fields to your schema)
    protected $guarded = [];
}