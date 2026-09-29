<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groupe scolaire : plusieurs établissements (sites) sous un même directeur,
 * ex. le collège et la maternelle/primaire. Les écoles qui partagent le même
 * group_code forment un groupe : chaque site garde ses données, sa caisse et
 * son personnel, et ses administrateurs peuvent passer d'un site à l'autre
 * et voir un tableau de bord commun. Réglé depuis la console plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('group_code', 60)->nullable()->index()->after('name');
            $table->string('site_label', 80)->nullable()->after('group_code');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['group_code', 'site_label']);
        });
    }
};
