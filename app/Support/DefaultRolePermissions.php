<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Droits de départ des rôles non administrateurs. Sans eux, un caissier ou
 * une secrétaire se connecte sur un menu vide : l'application paraît cassée.
 *
 * N'est appliqué qu'à un rôle qui n'a encore AUCUNE permission, pour ne
 * jamais écraser ce qu'un administrateur a réglé à la main depuis l'écran
 * Utilisateurs.
 */
class DefaultRolePermissions
{
    public const MAP = [
        'cashier' => [
            'dashboard.view', 'students.view',
            'payments.create', 'payments.view', 'payments.print', 'debtors.print',
        ],
        'secretary' => [
            'dashboard.view', 'students.view', 'students.create', 'students.update', 'students.reenroll',
            'classes.view', 'tranches.view', 'fees.view',
            'payments.view', 'payments.print', 'debtors.print', 'teachers.view',
        ],
        'accountant' => [
            'dashboard.view', 'students.view', 'classes.view', 'tranches.view', 'fees.view',
            'payments.view', 'payments.print', 'debtors.print', 'finance.view', 'teachers.view', 'audit.view',
        ],
    ];

    public static function apply(): void
    {
        foreach (self::MAP as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

            if (! $roleId || DB::table('role_permissions')->where('role_id', $roleId)->exists()) {
                continue;
            }

            $rows = DB::table('permissions')
                ->whereIn('code', $permissionCodes)
                ->pluck('id')
                ->map(fn ($permissionId) => ['role_id' => $roleId, 'permission_id' => $permissionId])
                ->all();

            DB::table('role_permissions')->insert($rows);
        }
    }
}
