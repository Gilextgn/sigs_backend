<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Models\Role;

/**
 * Crée (ou réaligne) le compte du propriétaire de la plateforme : un compte
 * sans établissement, qui ne voit que la console des écoles.
 *
 * En production sans shell, utiliser plutôt les variables PLATFORM_OWNER_*
 * lues par la migration add_platform_owner_role.
 */
class CreatePlatformOwner extends Command
{
    protected $signature = 'platform:create-owner {email} {password} {--name=Propriétaire SIGS} {--reset-password : Réimpose le mot de passe fourni même s\'il a été changé dans la console}';

    protected $description = 'Crée le compte propriétaire de la plateforme (sans établissement)';

    public function handle(): int
    {
        $role = Role::where('code', 'platform_owner')->first();

        if (! $role) {
            $this->error("Rôle platform_owner absent : lancez d'abord les migrations.");

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if ($user && $user->school_id !== null) {
            $this->error("Cet e-mail appartient déjà à un compte d'établissement : choisissez-en un autre.");

            return self::FAILURE;
        }

        $reset = (bool) $this->option('reset-password');

        // Le propriétaire a pu changer son e-mail dans la console : l'e-mail de
        // l'environnement ne désigne plus personne, mais il ne faut pas pour
        // autant créer un second compte propriétaire à chaque démarrage.
        if (! $user) {
            $user = User::whereNull('school_id')->where('role_id', $role->id)->orderBy('id')->first();

            if ($user && ! $reset) {
                $this->info("Compte propriétaire déjà présent ({$user->email}) : rien à faire.");

                return self::SUCCESS;
            }
        }

        if (! $user) {
            User::create([
                'email' => $this->argument('email'),
                'full_name' => (string) $this->option('name'),
                'password' => Hash::make((string) $this->argument('password')),
                'school_id' => null,
                'role_id' => $role->id,
                'status' => 'active',
            ]);
            $this->info('Compte propriétaire créé : '.$this->argument('email'));

            return self::SUCCESS;
        }

        // Le mot de passe choisi dans la console n'est jamais écrasé par un simple
        // redémarrage ; --reset-password (accès perdu) réimpose e-mail et mot de
        // passe de l'environnement.
        $keepPassword = $user->password_changed_at !== null && ! $reset;
        $attributes = ['school_id' => null, 'role_id' => $role->id, 'status' => 'active'];

        if (! $keepPassword) {
            $attributes += [
                'email' => $this->argument('email'),
                'password' => Hash::make((string) $this->argument('password')),
                'password_changed_at' => null,
            ];
        }

        $user->forceFill($attributes)->save();

        $this->info('Compte propriétaire prêt : '.$user->email.($keepPassword ? ' (mot de passe choisi dans la console conservé)' : ''));

        return self::SUCCESS;
    }
}
