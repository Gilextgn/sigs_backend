<?php

namespace Tests\Feature;

use App\Support\DefaultRolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sans droits de départ, un caissier se connecte sur un menu vide. La
 * migration doit les poser sur une base existante, sans jamais toucher un
 * rôle qu'un administrateur a déjà réglé.
 */
class DefaultRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_17_000001_grant_default_role_permissions.php');
        $migration->up();
    }

    private function codesFor(string $roleCode): array
    {
        return DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.code', $roleCode)
            ->orderBy('permissions.code')
            ->pluck('permissions.code')
            ->all();
    }

    public function test_it_grants_defaults_to_roles_without_permissions(): void
    {
        DB::table('role_permissions')
            ->whereIn('role_id', DB::table('roles')->whereIn('code', ['cashier', 'secretary', 'accountant'])->pluck('id'))
            ->delete();

        $this->runMigration();

        foreach (DefaultRolePermissions::MAP as $roleCode => $expected) {
            sort($expected);
            $this->assertSame($expected, $this->codesFor($roleCode), "Droits de départ incorrects pour {$roleCode}.");
        }
    }

    public function test_it_never_overwrites_a_role_already_configured(): void
    {
        $cashierId = DB::table('roles')->where('code', 'cashier')->value('id');
        DB::table('role_permissions')->where('role_id', $cashierId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $cashierId,
            'permission_id' => DB::table('permissions')->where('code', 'payments.view')->value('id'),
        ]);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(['payments.view'], $this->codesFor('cashier'));
    }
}
