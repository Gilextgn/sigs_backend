<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission de réinscription d'un élève pour l'année suivante. Même
 * pattern que 2026_09_11_000001_add_view_permissions : insertion
 * idempotente, seule façon de faire arriver de nouvelles permissions en
 * production (le seeder complet n'y tourne jamais).
 *
 * Aucune permission de "passage outre" : le blocage d'un élève non soldé
 * sur une année clôturée est strict, sans exception de rôle.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'students.reenroll' => 'Réinscrire un élève pour une nouvelle année',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $code => $label) {
            $exists = DB::table('permissions')->where('code', $code)->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'code' => $code,
                    'label' => $label,
                    'created_at' => now(),
                ]);
            }
        }

        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_keys(self::PERMISSIONS))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            $alreadyGranted = DB::table('role_permissions')
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $alreadyGranted) {
                DB::table('role_permissions')->insert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_keys(self::PERMISSIONS))
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
