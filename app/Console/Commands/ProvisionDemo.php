<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Models\Permission;
use Modules\Users\Models\Role;

/**
 * Prépare le compte de démonstration publique : un rôle qui ne porte que
 * des permissions de consultation, et un utilisateur rattaché à ce rôle.
 *
 * Idempotente : conçue pour tourner à chaque démarrage du conteneur de
 * démonstration, de sorte que le compte se rétablisse tout seul.
 */
class ProvisionDemo extends Command
{
    protected $signature = 'demo:provision
                            {--email=demo@sigs.com : Adresse du compte de démonstration}
                            {--password=demo1234 : Mot de passe destiné à être publié}';

    protected $description = 'Crée ou met à jour le rôle et le compte de démonstration en lecture seule';

    /**
     * Uniquement des permissions de consultation. Toute permission
     * d'écriture ajoutée ici rendrait la démonstration publique
     * modifiable par n'importe quel visiteur.
     */
    private const DEMO_PERMISSIONS = [
        'dashboard.view',
        'students.view',
        'classes.view',
        'tranches.view',
        'fees.view',
        'payments.view',
        'debtors.print',
        'finance.view',
        'teachers.view',
        'audit.view',
        'security.view',
        'settings.view',
    ];

    public function handle(): int
    {
        $role = Role::updateOrCreate(
            ['code' => 'demo'],
            ['label' => 'Démonstration (lecture seule)'],
        );

        $permissionIds = Permission::whereIn('code', self::DEMO_PERMISSIONS)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $email = (string) $this->option('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            // Le rôle et le mot de passe sont réalignés à chaque passage :
            // ce compte est public, son état ne doit pas dériver.
            $user->forceFill([
                'role_id' => $role->id,
                'status' => 'active',
                'password' => Hash::make((string) $this->option('password')),
            ])->save();
        } else {
            $user = User::create([
                'full_name' => 'Visiteur démonstration',
                'email' => $email,
                'password' => Hash::make((string) $this->option('password')),
                'role_id' => $role->id,
                'status' => 'active',
                'school_id' => 1,
            ]);
        }

        cache()->forget("user:{$user->id}:permissions");

        $this->lockOtherAccounts($user);

        $this->info("Compte de démonstration prêt : {$email} ({$permissionIds->count()} permissions en lecture).");

        return self::SUCCESS;
    }

    /**
     * Sur l'instance de démonstration, tout compte autre que celui de démo
     * doit être injoignable : le dépôt est public, donc le mot de passe
     * initial de l'administrateur (défini dans DatabaseSeeder) est connu de
     * tous et donnerait un accès en écriture.
     *
     * Le garde-fou sur DEMO_MODE évite qu'un lancement accidentel de cette
     * commande en production ne verrouille le vrai administrateur.
     */
    private function lockOtherAccounts(User $demoUser): void
    {
        if (! config('school.demo_mode')) {
            $this->warn('DEMO_MODE absent : les autres comptes sont laissés intacts.');

            return;
        }

        $locked = User::where('id', '!=', $demoUser->id)
            ->get()
            ->each(function (User $user) {
                $user->forceFill([
                    'status' => 'inactive',
                    'password' => Hash::make(bin2hex(random_bytes(24))),
                ])->save();
            })
            ->count();

        if ($locked > 0) {
            $this->info("{$locked} compte(s) non-démo désactivé(s) sur cette instance.");
        }
    }
}
