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
    protected $signature = 'platform:create-owner {email} {password} {--name=Propriétaire SIGS}';

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

        $attributes = [
            'school_id' => null,
            'role_id' => $role->id,
            'full_name' => (string) $this->option('name'),
            'password' => Hash::make((string) $this->argument('password')),
            'status' => 'active',
        ];

        $user ? $user->forceFill($attributes)->save() : User::create(['email' => $this->argument('email')] + $attributes);

        $this->info('Compte propriétaire prêt : '.$this->argument('email'));

        return self::SUCCESS;
    }
}
