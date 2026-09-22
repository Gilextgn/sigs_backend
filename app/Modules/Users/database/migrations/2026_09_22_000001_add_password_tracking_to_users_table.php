<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - must_change_password : le mot de passe a été choisi par quelqu'un d'autre
 *   (création d'une école, réinitialisation) : l'utilisateur doit en choisir
 *   un nouveau avant de travailler ;
 * - password_changed_at : dernier changement fait par l'utilisateur lui-même.
 *   Le démarrage ne réécrit plus alors le mot de passe du propriétaire depuis
 *   l'environnement (voir platform:create-owner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
            $table->dateTime('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};
