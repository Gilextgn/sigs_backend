<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La migration qui ajoute les permissions de consultation est ce qui évite
 * que l'administrateur en production perde l'accès aux classes, tranches,
 * frais, enseignants, paie et paramètres après le déploiement : le seeder
 * complet n'y tourne plus, seules les migrations s'exécutent.
 */
class ViewPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const VIEW_PERMISSIONS = [
        'classes.view',
        'tranches.view',
        'fees.view',
        'teachers.view',
        'settings.view',
    ];

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_11_000001_add_view_permissions.php');
        $migration->up();
    }

    public function test_it_restores_the_view_permissions_on_an_existing_database(): void
    {
        // Reproduit l'état de la base de production avant déploiement :
        // le rôle admin existe déjà, mais sans les nouvelles permissions.
        $removedIds = DB::table('permissions')->whereIn('code', self::VIEW_PERMISSIONS)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $removedIds)->delete();
        DB::table('permissions')->whereIn('id', $removedIds)->delete();

        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');
        $this->assertSame(0, $this->grantedViewPermissionCount($adminRoleId));

        $this->runMigration();

        $this->assertSame(
            count(self::VIEW_PERMISSIONS),
            $this->grantedViewPermissionCount($adminRoleId),
            "L'administrateur doit récupérer toutes les permissions de consultation.",
        );
    }

    public function test_it_can_be_replayed_without_duplicating_rows(): void
    {
        $this->runMigration();
        $this->runMigration();

        $this->assertSame(
            count(self::VIEW_PERMISSIONS),
            DB::table('permissions')->whereIn('code', self::VIEW_PERMISSIONS)->count(),
        );
    }

    private function grantedViewPermissionCount(int $roleId): int
    {
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->whereIn('permissions.code', self::VIEW_PERMISSIONS)
            ->count();
    }
}
