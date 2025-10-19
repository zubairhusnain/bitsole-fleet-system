<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\\Database\\Factories\\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Role mapping
     * 0 = user
     * 1 = fleet_manager
     * 2 = distributor
     * 3 = admin (default)
     */
    public const ROLE_USER = 0;
    public const ROLE_FLEET_MANAGER = 1;
    public const ROLE_DISTRIBUTOR = 2;
    public const ROLE_ADMIN = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'traccarSession',
        'distributor_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'integer',
            'distributor_id' => 'integer',
        ];
    }

    // Convenience helpers (default to admin when role is null)
    public function isAdmin(): bool { return (int)($this->role ?? self::ROLE_ADMIN) === self::ROLE_ADMIN; }
    public function isDistributor(): bool { return (int)($this->role ?? self::ROLE_ADMIN) === self::ROLE_DISTRIBUTOR; }
    public function isFleetManager(): bool { return (int)($this->role ?? self::ROLE_ADMIN) === self::ROLE_FLEET_MANAGER; }
}
