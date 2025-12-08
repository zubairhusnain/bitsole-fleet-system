<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    protected $table = 'user_permissions';
    protected $fillable = [
        'user_id',
        'module_key',
        'can_access',
        'can_read',
        'can_create',
        'can_update',
        'can_delete',
    ];
    protected $casts = [
        'user_id' => 'integer',
        'can_access' => 'boolean',
        'can_read' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
