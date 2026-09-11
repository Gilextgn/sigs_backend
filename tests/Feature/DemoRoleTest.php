<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

/**
 * Le compte de démonstration est public : cette classe est la garantie que
 * l'API refuse bien toute écriture de sa part. Si une future route d'écriture
 * est ajoutée sans permission, un de ces tests doit tomber.
 */
class DemoRoleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function demoUser(): User
    {
        $this->artisan('demo:provision')->assertSuccessful();

        return User::where('email', 'demo@sigs.com')->firstOrFail();
    }

    private function aStudent(SchoolClass $class): Student
    {
        $guardian = Guardian::create([
            'full_name' => 'Tuteur Démo',
            'relationship_label' => 'Père',
            'phone' => '97000000',
        ]);

        return Student::create([
            'school_id' => 1,
            'class_id' => $class->id,
            'guardian_id' => $guardian->id,
            'registration_year' => (int) date('Y'),
            'registration_sequence' => 1,
            'matricule' => 'ELV-'.date('Y').'-000001',
            'first_name' => 'Élève',
            'last_name' => 'Démo',
            'status' => 'active',
        ]);
    }

    public function test_the_demo_account_can_read_the_main_sections(): void
    {
        $user = $this->demoUser();
        $this->createSchoolClass();

        foreach ([
            '/api/dashboard/summary',
            '/api/students',
            '/api/classes',
            '/api/tranches',
            '/api/fees',
            '/api/payments',
            '/api/debtors',
            '/api/teachers',
            '/api/subjects',
            '/api/schedules',
            '/api/payroll',
            '/api/settings',
            '/api/audit-logs',
        ] as $endpoint) {
            $this->actingAs($user)
                ->getJson($endpoint)
                ->assertOk("L'endpoint {$endpoint} devrait être lisible par le compte de démonstration.");
        }
    }

    public function test_the_demo_account_cannot_write_anywhere(): void
    {
        $user = $this->demoUser();
        $class = $this->createSchoolClass();
        $student = $this->aStudent($class);
        $installment = TuitionInstallment::create([
            'class_id' => $class->id,
            'label' => '1ère tranche',
            'amount' => 50000,
        ]);

        $writes = [
            ['postJson', '/api/students', ['class_id' => $class->id, 'first_name' => 'X', 'last_name' => 'Y', 'guardian' => ['full_name' => 'Z', 'relationship_label' => 'Père', 'phone' => '9700']]],
            ['putJson', "/api/students/{$student->id}", ['first_name' => 'Modifié']],
            ['deleteJson', "/api/students/{$student->id}", []],
            ['postJson', '/api/payments', ['student_id' => $student->id, 'items' => [['item_type' => 'TRANCHE', 'tuition_installment_id' => $installment->id, 'paid_amount' => 1000]]]],
            ['postJson', '/api/classes', ['cycle_id' => 1, 'code' => 'NEW', 'label' => 'Nouvelle', 'tuition_amount' => 1000]],
            ['deleteJson', "/api/classes/{$class->id}", []],
            ['postJson', '/api/tranches', ['class_id' => $class->id, 'label' => 'T', 'amount' => 1000]],
            ['deleteJson', "/api/tranches/{$installment->id}", []],
            ['postJson', '/api/fees', ['label' => 'Frais', 'amount' => 1000, 'class_ids' => []]],
            ['postJson', '/api/subjects', ['code' => 'NEW', 'label' => 'Nouvelle matière']],
            ['postJson', '/api/teachers', ['full_name' => 'Prof', 'hourly_rate' => 1000]],
            ['postJson', '/api/users', ['full_name' => 'Intrus', 'email' => 'intrus@sigs.com', 'password' => 'motdepasse', 'role_id' => 1]],
            ['putJson', '/api/settings', ['school_name' => 'Piraté']],
        ];

        foreach ($writes as [$method, $endpoint, $payload]) {
            $this->actingAs($user)
                ->{$method}($endpoint, $payload)
                ->assertForbidden("L'écriture {$method} {$endpoint} devrait être refusée au compte de démonstration.");
        }

        $this->assertSame('Élève', $student->fresh()->first_name);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_provisioning_disables_the_other_accounts_in_demo_mode(): void
    {
        // Le dépôt étant public, le mot de passe initial de l'admin est connu :
        // sur l'instance de démonstration il ne doit plus permettre de se connecter.
        config(['school.demo_mode' => true]);

        $this->demoUser();

        $admin = User::where('email', 'admin@sigs.com')->firstOrFail();
        $this->assertSame('inactive', $admin->status);
    }

    public function test_the_demo_dataset_populates_the_screens(): void
    {
        $this->demoUser();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        // Le jeu fictif doit remplir les écrans : sans élèves ni paiements,
        // la démonstration publique n'a rien à montrer.
        $this->assertDatabaseCount('students', 10);
        $this->assertGreaterThan(0, \Modules\Payments\Models\Payment::count());
        $this->assertDatabaseHas('school_settings', [
            'setting_key' => 'school_name',
            'setting_value' => 'Groupe Scolaire Démo',
        ]);
    }

    public function test_provisioning_leaves_other_accounts_alone_outside_demo_mode(): void
    {
        // Garde-fou : lancer la commande par erreur en production ne doit pas
        // verrouiller le véritable administrateur.
        config(['school.demo_mode' => false]);

        $this->demoUser();

        $this->assertSame('active', User::where('email', 'admin@sigs.com')->firstOrFail()->status);
    }
}
