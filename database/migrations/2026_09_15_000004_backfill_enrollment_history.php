<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les élèves et paiements antérieurs à l'inscription par année
 * (academic_year_id encore NULL) à l'année active, et leur crée
 * l'historique d'inscription correspondant.
 *
 * C'est une migration et non une commande artisan parce que l'hébergement
 * (Render, plan gratuit) n'offre aucun accès shell : seules les migrations
 * lancées par entrypoint.sh au démarrage peuvent toucher la base.
 *
 * Volontairement en requêtes brutes plutôt qu'en Eloquent : une migration
 * doit rester valable même si les modèles évoluent, et cela évite de
 * déchiffrer/rechiffrer les champs sensibles (nom, prénom) alors qu'on ne
 * touche qu'à academic_year_id.
 *
 * Best-effort : il n'existe pas d'historique réel à reconstituer, tout
 * l'existant est rattaché à l'année active du moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        $activeYearId = DB::table('academic_years')->where('is_active', true)->value('id');

        // Base neuve (aucune année active) : rien à rattacher.
        if (! $activeYearId) {
            return;
        }

        $students = DB::table('students')
            ->whereNull('academic_year_id')
            ->select('id', 'class_id')
            ->get();

        foreach ($students as $student) {
            DB::table('students')->where('id', $student->id)->update(['academic_year_id' => $activeYearId]);

            $alreadyEnrolled = DB::table('student_enrollments')
                ->where('student_id', $student->id)
                ->where('academic_year_id', $activeYearId)
                ->exists();

            if (! $alreadyEnrolled) {
                DB::table('student_enrollments')->insert([
                    'student_id' => $student->id,
                    'academic_year_id' => $activeYearId,
                    'class_id' => $student->class_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Les paiements suivent l'année de l'élève payeur, désormais renseignée.
        DB::table('payments')
            ->whereNull('academic_year_id')
            ->update([
                'academic_year_id' => DB::raw('(select academic_year_id from students where students.id = payments.student_id)'),
            ]);
    }

    public function down(): void
    {
        // Irréversible par nature : on ne sait pas distinguer les lignes
        // rattachées par ce backfill de celles créées normalement depuis.
    }
};
