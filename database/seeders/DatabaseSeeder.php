<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\AcademicYears\Models\SchoolSetting;
use Modules\SchoolClasses\Models\SchoolCycle;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;
use Modules\Teachers\Models\Subject;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Rôles (repris de la migration 001)
        $roles = [
            'admin' => 'Administrateur',
            'secretary' => 'Secrétaire',
            'cashier' => 'Caissier',
            'accountant' => 'Comptable',
        ];
        foreach ($roles as $code => $label) {
            Role::updateOrCreate(['code' => $code], ['label' => $label]);
        }

        // Catalogue de permissions modulaires (repris de la migration 001)
        $permissions = [
            'dashboard.view' => 'Voir le dashboard',
            'students.create' => 'Inscrire un élève',
            'students.update' => 'Modifier un élève',
            'students.delete' => 'Supprimer / archiver un élève',
            'students.view' => 'Consulter la fiche élève',
            'classes.view' => 'Consulter les classes',
            'classes.manage' => 'Gérer les classes et scolarités',
            'tranches.view' => 'Consulter les tranches',
            'tranches.manage' => 'Gérer les tranches',
            'fees.view' => 'Consulter les autres frais',
            'fees.manage' => 'Gérer les autres frais',
            'payments.create' => 'Encaisser un paiement',
            'payments.view' => 'Consulter les paiements',
            'payments.delete' => 'Supprimer un paiement',
            'payments.print' => 'Imprimer un reçu',
            'debtors.print' => 'Liste et impression des débiteurs',
            'teachers.view' => 'Consulter enseignants, emploi du temps et paie',
            'teachers.manage' => 'Gérer enseignants et paie',
            'finance.view' => 'Consulter les finances',
            'users.manage' => 'Gérer utilisateurs et droits',
            'audit.view' => "Consulter le journal d'audit",
            'settings.view' => 'Consulter les paramètres établissement',
            'settings.manage' => 'Paramètres établissement',
            'security.view' => 'Voir la politique de sécurité',
        ];
        foreach ($permissions as $code => $label) {
            Permission::updateOrCreate(['code' => $code], ['label' => $label]);
        }

        // Le rôle admin reçoit toutes les permissions
        $admin = Role::where('code', 'admin')->first();
        $admin->permissions()->sync(Permission::pluck('id'));

        // Cycles scolaires
        foreach ([
            ['code' => 'maternelle', 'label' => 'Maternelle', 'sort_order' => 1],
            ['code' => 'primaire', 'label' => 'Primaire', 'sort_order' => 2],
            ['code' => 'cycle_1', 'label' => 'Collège 1er cycle', 'sort_order' => 3],
            ['code' => 'cycle_2', 'label' => 'Collège 2ème cycle', 'sort_order' => 4],
        ] as $cycle) {
            SchoolCycle::updateOrCreate(['code' => $cycle['code']], $cycle);
        }

        // Année scolaire active par défaut
        AcademicYear::updateOrCreate(
            ['code' => '2026-2027'],
            ['label' => 'Année 2026-2027', 'is_active' => true, 'date_start' => '2026-09-01', 'date_end' => '2027-07-31']
        );

        // Paramètres établissement par défaut
        foreach ([
            'school_name' => 'Mon École',
            'matricule_prefix' => 'ELV',
            'currency' => 'XOF',
        ] as $key => $value) {
            SchoolSetting::updateOrCreate(['school_id' => 1, 'setting_key' => $key], ['setting_value' => $value]);
        }

        foreach ([
            ['code' => 'francais', 'label' => 'Français'],
            ['code' => 'mathematiques', 'label' => 'Mathématiques'],
            ['code' => 'anglais', 'label' => 'Anglais'],
            ['code' => 'sciences', 'label' => 'Sciences'],
        ] as $subject) {
            Subject::updateOrCreate(['school_id' => 1, 'code' => $subject['code']], $subject);
        }

        // Compte admin de démarrage (à changer immédiatement en production)
        // firstOrCreate (et non updateOrCreate) : on ne veut pas écraser le
        // mot de passe si l'admin l'a déjà changé lors d'un re-seed.
        User::firstOrCreate(
            ['email' => 'admin@sigs.com'],
            [
                'full_name' => 'Administrateur SIGS',
                'password' => Hash::make('admin'),
                'role_id' => $admin->id,
                'status' => 'active',
                'school_id' => 1,
            ]
        );
    }
}
