<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\AcademicYears\Models\SchoolSetting;
use Modules\Fees\Models\FeeType;
use Modules\Payments\Services\PaymentService;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentEnrollment;
use Modules\Students\Services\MatriculeGenerator;
use Modules\Tranches\Models\TuitionInstallment;

/**
 * Jeu de données entièrement fictif pour l'instance de démonstration
 * publique. Il ne doit JAMAIS tourner sur l'instance de production : c'est
 * entrypoint.sh qui en réserve l'exécution au cas DEMO_MODE=true.
 *
 * Les noms sont inventés et l'établissement s'appelle explicitement
 * « Groupe Scolaire Démo » pour qu'aucun visiteur ne puisse confondre ces
 * enregistrements avec des données réelles.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Student::query()->exists()) {
            $this->command?->info('Données de démonstration déjà présentes, rien à faire.');

            return;
        }

        foreach ([
            'school_name' => 'Groupe Scolaire Démo',
            'matricule_prefix' => 'ELV',
            'currency' => 'XOF',
        ] as $key => $value) {
            SchoolSetting::updateOrCreate(
                ['school_id' => 1, 'setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        $classes = $this->createClasses();
        $this->createFeeTypes($classes);
        $this->createStudentsAndPayments($classes);

        $this->command?->info('Données de démonstration générées.');
    }

    /** @return array<string, SchoolClass> */
    private function createClasses(): array
    {
        $definitions = [
            ['cycle' => 'maternelle', 'code' => 'MS-A', 'label' => 'Maternelle Moyenne Section A', 'tuition' => 150000],
            ['cycle' => 'primaire', 'code' => 'CE2-A', 'label' => 'CE2 A', 'tuition' => 200000],
            ['cycle' => 'primaire', 'code' => 'CM2-A', 'label' => 'CM2 A', 'tuition' => 220000],
            ['cycle' => 'cycle_1', 'code' => '5EME-B', 'label' => '5ème B', 'tuition' => 280000],
            ['cycle' => 'cycle_2', 'code' => '2NDE-C', 'label' => '2nde C', 'tuition' => 350000],
        ];

        $classes = [];

        foreach ($definitions as $definition) {
            $cycle = SchoolCycle::where('code', $definition['cycle'])->first();

            if (! $cycle) {
                continue;
            }

            $class = SchoolClass::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'school_id' => 1,
                    'cycle_id' => $cycle->id,
                    'label' => $definition['label'],
                    'tuition_amount' => $definition['tuition'],
                    'is_active' => true,
                ],
            );

            // Trois tranches réparties sur l'année, sans jamais dépasser la
            // scolarité de la classe (règle métier appliquée côté API).
            $amounts = [
                round($definition['tuition'] * 0.4),
                round($definition['tuition'] * 0.3),
                round($definition['tuition'] * 0.3),
            ];
            $dueDates = ['-10-05', '-01-10', '-04-05'];
            $year = (int) date('Y');

            foreach ($amounts as $index => $amount) {
                TuitionInstallment::updateOrCreate(
                    ['class_id' => $class->id, 'label' => ($index + 1).'ère tranche'],
                    [
                        'amount' => $amount,
                        'due_date' => ($index === 0 ? $year : $year + 1).$dueDates[$index],
                    ],
                );
            }

            $classes[$definition['code']] = $class;
        }

        return $classes;
    }

    /** @param array<string, SchoolClass> $classes */
    private function createFeeTypes(array $classes): void
    {
        $definitions = [
            ['label' => 'Cantine', 'category' => 'Restauration', 'amount' => 45000, 'mandatory' => false],
            ['label' => 'Tenue scolaire', 'category' => 'Équipement', 'amount' => 15000, 'mandatory' => true],
            ['label' => 'Transport', 'category' => 'Transport', 'amount' => 60000, 'mandatory' => false],
        ];

        foreach ($definitions as $definition) {
            $fee = FeeType::updateOrCreate(
                ['label' => $definition['label']],
                [
                    'category' => $definition['category'],
                    'amount' => $definition['amount'],
                    'is_active' => true,
                    'is_mandatory' => $definition['mandatory'],
                ],
            );

            $fee->classes()->sync(collect($classes)->pluck('id'));
        }
    }

    /** @param array<string, SchoolClass> $classes */
    private function createStudentsAndPayments(array $classes): void
    {
        $cashier = User::where('email', 'demo@sigs.com')->first() ?? User::first();
        $matricules = app(MatriculeGenerator::class);
        $payments = app(PaymentService::class);

        // Prénoms/noms inventés : aucun rapport avec des élèves réels.
        $roster = [
            ['MS-A', 'Awa', 'Kponou', 'F', '2020-03-14', 'Reine Kponou', 'Mère', '97000011'],
            ['MS-A', 'Ismaël', 'Dossou-Gbete', 'M', '2020-07-02', 'Félix Dossou-Gbete', 'Père', '97000012'],
            ['CE2-A', 'Nadia', 'Agossou', 'F', '2017-11-23', 'Clarisse Agossou', 'Mère', '97000013'],
            ['CE2-A', 'Yassine', 'Tchibozo', 'M', '2017-05-09', 'Rachid Tchibozo', 'Père', '97000014'],
            ['CM2-A', 'Chantal', 'Hounkpatin', 'F', '2015-02-18', 'Sylvie Hounkpatin', 'Mère', '97000015'],
            ['CM2-A', 'Serge', 'Adjovi', 'M', '2015-09-30', 'Pascal Adjovi', 'Tuteur légal', '97000016'],
            ['5EME-B', 'Fatou', 'Zinsou', 'F', '2012-06-21', 'Bernadette Zinsou', 'Mère', '97000017'],
            ['5EME-B', 'Moussa', 'Akplogan', 'M', '2012-12-04', 'Idrissou Akplogan', 'Père', '97000018'],
            ['2NDE-C', 'Estelle', 'Sagbo', 'F', '2009-08-16', 'Hortense Sagbo', 'Mère', '97000019'],
            ['2NDE-C', 'Rodrigue', 'Ahouansou', 'M', '2009-04-27', 'Gaston Ahouansou', 'Père', '97000020'],
        ];

        // Les élèves de démonstration doivent être rattachés à l'année active,
        // sinon la clôture d'année et la réinscription — précisément ce que la
        // démonstration doit montrer — n'ont aucun élève sur quoi s'appuyer.
        $activeYear = AcademicYear::where('is_active', true)->first();

        foreach ($roster as $index => [$classCode, $firstName, $lastName, $gender, $birthDate, $guardianName, $relationship, $phone]) {
            if (! isset($classes[$classCode])) {
                continue;
            }

            $class = $classes[$classCode];

            $guardian = Guardian::create([
                'full_name' => $guardianName,
                'relationship_label' => $relationship,
                'phone' => $phone,
                'address' => 'Cotonou',
            ]);

            $student = Student::create([
                ...$matricules->generate(),
                'school_id' => 1,
                'class_id' => $class->id,
                'academic_year_id' => $activeYear?->id,
                'guardian_id' => $guardian->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'status' => 'active',
            ]);

            if ($activeYear) {
                StudentEnrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $activeYear->id,
                    'class_id' => $class->id,
                ]);
            }

            // Deux élèves sur trois ont réglé leur première tranche : les
            // écrans « débiteurs » et « reste dû » ont ainsi du relief.
            if ($index % 3 === 2 || ! $cashier) {
                continue;
            }

            $installment = TuitionInstallment::where('class_id', $class->id)
                ->orderBy('id')
                ->first();

            if ($installment) {
                $payments->create($student->id, [[
                    'item_type' => 'TRANCHE',
                    'tuition_installment_id' => $installment->id,
                    'paid_amount' => (float) $installment->amount,
                ]], $cashier->id);
            }
        }
    }
}
