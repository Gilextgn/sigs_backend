<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // User::permissions() met ses résultats en cache 60s par id
        // d'utilisateur. Comme RefreshDatabase réattribue les mêmes ids d'un
        // test à l'autre, un cache non vidé ferait fuiter les permissions
        // d'un test vers le suivant.
        cache()->flush();
    }

    /**
     * Crée un utilisateur doté du rôle "admin" (toutes les permissions), ou
     * d'un rôle sur mesure ne portant que les permissions demandées.
     *
     * @param  string[]|null  $permissions  null = toutes les permissions
     */
    protected function userWithPermissions(?array $permissions = null): User
    {
        if ($permissions === null) {
            $role = Role::where('code', 'admin')->firstOrFail();
        } else {
            $role = Role::create(['code' => 'test_role_'.uniqid(), 'label' => 'Rôle de test']);
            $role->permissions()->sync(Permission::whereIn('code', $permissions)->pluck('id'));
        }

        return User::create([
            'full_name' => 'Utilisateur de test',
            'email' => 'test'.uniqid().'@sigs.test',
            'password' => 'motdepasse',
            'role_id' => $role->id,
            'status' => 'active',
            'school_id' => 1,
        ]);
    }

    /**
     * Classe scolaire minimale rattachée au premier cycle seedé.
     */
    protected function createSchoolClass(float $tuitionAmount = 300000): SchoolClass
    {
        $cycle = SchoolCycle::firstOrFail();

        return SchoolClass::create([
            'school_id' => 1,
            'cycle_id' => $cycle->id,
            'code' => 'CLS-'.uniqid(),
            'label' => 'Classe de test',
            'tuition_amount' => $tuitionAmount,
            'is_active' => true,
        ]);
    }
}
