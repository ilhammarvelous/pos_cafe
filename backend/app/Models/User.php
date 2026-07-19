<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements JwtSubject
{
    use HasFactory, Notifiable, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'last_activity_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'role' => 'string',
        'status' => 'string',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function getJwtIdentifier()
    {
        return $this->getKey();
    }

    public function getJwtCustomClaims()
    {
        return [
            'email' => $this->email,
            'role' => $this->role,
        ];
    }

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class, 'created_by');
    }

    public function orderItemsPrepared()
    {
        return $this->hasMany(OrderItem::class, 'prepared_by');
    }
}
