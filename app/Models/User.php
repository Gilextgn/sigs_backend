<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Users\Models\Role;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, \App\Support\Audited;

    /** Nom affiché dans le journal d'audit ; le téléphone chiffré n'y est jamais recopié. */
    protected string $auditName = 'utilisateur';

    protected array $auditHidden = ['phone', 'password', 'password_changed_at'];

    protected $fillable = [
        'school_id',
        'role_id',
        'full_name',
        'email',
        'password',
        'phone',
        'status',
        'last_login_at',
        'must_change_password',
        'password_changed_at',
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
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Une route « /users/{user} » ne résout que les utilisateurs de l'école
     * courante : l'identifiant d'un compte d'une autre école donne un 404.
     * (Pas de scope global : il fausserait la lecture de la session.)
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->where($field ?? $this->getRouteKeyName(), $value);

        if (\App\Support\CurrentSchool::isBound()) {
            $query->where('school_id', \App\Support\CurrentSchool::id());
        }

        return $query->first();
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
            )->withPivot('granted')->get(['permissions.code']);

            // Droits ajoutés (granted = 1) et droits du rôle retirés à ce compte (granted = 0).
            $granted = $overrides->where('pivot.granted', true)->pluck('code')->all();
            $revoked = $overrides->where('pivot.granted', false)->pluck('code')->all();

            return array_values(array_diff(array_unique([...$rolePermissions, ...$granted]), $revoked));
        });
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions(), true);
    }
}
