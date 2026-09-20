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

            $this->syncPermissionOverrides($user, $data['permissions'] ?? []);

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

            $user->update(collect($data)->except('permissions')->all());

            if (array_key_exists('permissions', $data)) {
                $this->syncPermissionOverrides($user, $data['permissions']);
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
    private function syncPermissionOverrides(User $user, array $permissionCodes): void
    {
        $ids = Permission::whereIn('code', $permissionCodes)->pluck('id', 'code');

        $syncData = collect($ids)->mapWithKeys(fn ($id) => [$id => ['granted' => true]])->all();

        $user->belongsToMany(Permission::class, 'user_permissions')->sync($syncData);
        cache()->forget("user:{$user->id}:permissions");
    }
}
