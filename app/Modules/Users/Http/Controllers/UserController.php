<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Models\Permission;

class UserController extends Controller
{
    public function index()
    {
        return User::with('role')
            ->where('school_id', \App\Support\CurrentSchool::id())
            ->orderBy('full_name')
            ->paginate(request()->integer('per_page', 20));
    }

    /**
     * Codes de permission individuellement cochés pour cet utilisateur
     * (surcharges au-delà de celles héritées de son rôle), pour pré-remplir
     * le formulaire d'édition côté frontend.
     */
    private function overriddenPermissionCodes(User $user): array
    {
        return $user->belongsToMany(Permission::class, 'user_permissions')
            ->wherePivot('granted', 1)
            ->pluck('code')
            ->all();
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'role_id' => $data['role_id'],
                'status' => $data['status'] ?? 'active',
                'school_id' => \App\Support\CurrentSchool::id(),
            ]);

            $this->syncPermissionOverrides($user, $data['permissions'] ?? [], ($data['permissions_mode'] ?? null) === 'effective');

            return $user;
        });

        return response()->json($user->load('role'), 201);
    }

    public function show(User $user)
    {
        $user->load('role');

        return response()->json([
            ...$user->toArray(),
            'permission_overrides' => $this->overriddenPermissionCodes($user),
            // Droits réels du compte (rôle + ajouts − retraits) : ce que le formulaire coche.
            'effective_permissions' => $user->permissions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $user) {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update(collect($data)->except(['permissions', 'permissions_mode'])->all());

            if (array_key_exists('permissions', $data)) {
                $this->syncPermissionOverrides($user->fresh('role'), $data['permissions'], ($data['permissions_mode'] ?? null) === 'effective');
            }
        });

        return $user->fresh('role');
    }

    public function destroy(User $user)
    {
        abort_if($user->id === request()->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');

        $user->delete();

        return response()->noContent();
    }

    /**
     * Reproduit le comportement "permissions par checkbox" de l'ancienne UI :
     * chaque permission cochée devient une surcharge explicite (granted=1)
     * dans user_permissions, en plus des permissions héritées du rôle.
     */
    private function syncPermissionOverrides(User $user, array $permissionCodes, bool $effective = false): void
    {
        if ($effective) {
            // Liste complète voulue : on ajoute ce que le rôle ne donne pas, on retire ce qu'il donne en trop.
            $roleCodes = $user->role?->permissions()->pluck('code')->all() ?? [];
            // Garde-fou : on ne se retire pas à soi-même le droit de gérer les utilisateurs.
            if ($user->id === request()->user()?->id && request()->user()->hasPermission('users.manage')) {
                $permissionCodes = array_values(array_unique([...$permissionCodes, 'users.manage']));
            }
            $grantIds = Permission::whereIn('code', array_diff($permissionCodes, $roleCodes))->pluck('id');
            $revokeIds = Permission::whereIn('code', array_diff($roleCodes, $permissionCodes))->pluck('id');
            $syncData = $grantIds->mapWithKeys(fn ($id) => [$id => ['granted' => true]])->all()
                + $revokeIds->mapWithKeys(fn ($id) => [$id => ['granted' => false]])->all();
        } else {
            $ids = Permission::whereIn('code', $permissionCodes)->pluck('id', 'code');
            $syncData = collect($ids)->mapWithKeys(fn ($id) => [$id => ['granted' => true]])->all();
        }

        $result = $user->belongsToMany(Permission::class, 'user_permissions')->sync($syncData);
        cache()->forget("user:{$user->id}:permissions");

        // Droits accordés ou retirés : traçabilité (la table de liaison échappe au suivi automatique).
        $codes = fn (array $permissionIds) => Permission::whereIn('id', $permissionIds)->pluck('code')->all();
        if ($result['attached'] !== [] || $result['detached'] !== []) {
            \Modules\Security\Models\AuditLog::record('user.permissions_changed', 'utilisateur', (string) $user->id, [
                'user' => $user->full_name,
                'granted' => $codes($result['attached']),
                'revoked' => $codes($result['detached']),
            ]);
        }
    }
}
