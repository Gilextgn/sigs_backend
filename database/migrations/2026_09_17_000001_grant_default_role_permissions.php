<?php

use App\Support\DefaultRolePermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Le seeder complet ne tourne jamais en production : c'est cette migration
 * qui donne leurs droits de départ aux rôles caissier, secrétaire et
 * comptable sur une base déjà déployée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DefaultRolePermissions::apply();
    }

    public function down(): void
    {
        // Rien à défaire : impossible de distinguer ces droits de ceux
        // qu'un administrateur aurait ajoutés ensuite.
    }
};
