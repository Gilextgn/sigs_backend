<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Crée le rôle du propriétaire de la plateforme (celui qui crée, suspend et
 * réactive les écoles) et, si les variables d'environnement sont fournies, son
 * compte. Sans shell sur l'hébergement, la migration est le seul moyen fiable
 * de le créer : PLATFORM_OWNER_EMAIL / PLATFORM_OWNER_PASSWORD / PLATFORM_OWNER_NAME.
 *
 * Ce rôle n'a AUCUNE permission d'établissement : il ne peut lire ni élèves,
 * ni paiements, ni classes. Et le rôle admin des écoles ne reçoit jamais
 * platform.manage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('permissions')->where('code', 'platform.manage')->exists()) {
            DB::table('permissions')->insert([
                'code' => 'platform.manage',
                'label' => 'Gérer les établissements (plateforme)',
                'created_at' => now(),
            ]);
        }

        if (! DB::table('roles')->where('code', 'platform_owner')->exists()) {
            DB::table('roles')->insert([
                'code' => 'platform_owner',
                'label' => 'Propriétaire de la plateforme',
                'created_at' => now(),
            ]);
        }

        $roleId = DB::table('roles')->where('code', 'platform_owner')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'platform.manage')->value('id');

        if (! DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->exists()) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }

        $email = env('PLATFORM_OWNER_EMAIL');
        $password = env('PLATFORM_OWNER_PASSWORD');

        if ($email && $password && ! DB::table('users')->where('email', $email)->exists()) {
            DB::table('users')->insert([
                'school_id' => null,
                'role_id' => $roleId,
                'full_name' => env('PLATFORM_OWNER_NAME', 'Propriétaire SIGS'),
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
