<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comment l'enseignant est payé :
 *  - hourly  : à l'heure faite, d'après les présences (tarif de chaque
 *              affectation) — le salaire mensuel n'a alors aucun sens ;
 *  - monthly : salaire fixe, saisi sur sa fiche.
 *
 * La paie se calculait déjà à partir des séances : les fiches qui portaient
 * un salaire mensuel sont donc reprises comme telles, les autres à l'heure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('pay_mode', 10)->default('hourly');
        });

        DB::table('teachers')->whereNotNull('monthly_salary')->where('monthly_salary', '>', 0)->update(['pay_mode' => 'monthly']);
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('pay_mode');
        });
    }
};
