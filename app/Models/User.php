<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Users\Models\Role;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'school_id',
        'role_id',
        'full_name',
        'email',
        'password',
        'phone',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'phone' => 'encrypted',
            'last_login_at' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Permissions effectives = permissions du rôle + surcharges individuelles
     * (user_permissions.granted), miroir de la vue SQL v_user_permissions.
     */
    public function permissions(): array
    {
        return cache()->remember("user:{$this->id}:permissions", 60, function () {
            $rolePermissions = $this->role?->permissions()->pluck('code')->all() ?? [];

            $overrides = $this->belongsToMany(
                \Modules\Users\Models\Permission::class,
                'user_permissions'
            )->wherePivot('granted', 1)->pluck('code')->all();

            return array_values(array_unique([...$rolePermissions, ...$overrides]));
        });
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions(), true);
    }
}
