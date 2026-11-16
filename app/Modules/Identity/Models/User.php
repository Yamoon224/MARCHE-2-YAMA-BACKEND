<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Contracts\PermissionHolder;
use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property Role $role
 */
class User extends Authenticatable implements PermissionHolder
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'locale', 'is_guest', 'contact_email', 'contact_phone'];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_guest' => 'boolean',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role->can($permission);
    }
}
