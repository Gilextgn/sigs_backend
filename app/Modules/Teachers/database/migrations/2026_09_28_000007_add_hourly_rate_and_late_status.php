<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - teachers.hourly_rate : tarif d'un enseignant payé à l'heure, saisi sur
 *   sa fiche. Une affectation sans tarif propre reprend celui-ci.
 * - teacher_attendances.status devient une chaîne pour accueillir « late »
 *   (en retard : les minutes de retard sont déduites des heures payées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('monthly_salary');
        });

        // Tarif de départ : le plus courant de ses affectations actuelles.
        foreach (DB::table('teacher_assignments')->select('teacher_id', DB::raw('MAX(hourly_rate) as rate'))->groupBy('teacher_id')->get() as $row) {
            DB::table('teachers')->where('id', $row->teacher_id)->whereNull('hourly_rate')->update(['hourly_rate' => $row->rate]);
        }

        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->string('status', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
