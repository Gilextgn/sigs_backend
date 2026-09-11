<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les permissions de consultation ont été introduites pour permettre un
 * accès en lecture seule (compte de démonstration, rôles non
 * administrateurs). Les routes GET correspondantes les exigent désormais.
 *
 * Sans cette migration, une base déjà déployée n'aurait pas ces lignes et
 * l'administrateur perdrait l'accès aux classes, tranches, frais,
 * enseignants, paie et paramètres : le seeder complet ne tourne plus en
 * production, seules les migrations le font.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'classes.view' => 'Consulter les classes',
        'tranches.view' => 'Consulter les tranches',
        'fees.view' => 'Consulter les autres frais',
        'teachers.view' => 'Consulter enseignants, emploi du temps et paie',
        'settings.view' => 'Consulter les paramètres établissement',
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
