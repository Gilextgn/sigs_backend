<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige le rattachement fait par 2026_09_15_000004.
 *
 * Ce backfill attachait les élèves existants à l'année ACTIVE. Or les élèves
 * déjà en base sont ceux de l'année écoulée : c'est justement pour l'année
 * active qu'il reste à les réinscrire. Les laisser ainsi les faisait passer
 * pour « déjà inscrits », ce qui rendait la rentrée impossible à saisir.
 *
 * On les replace donc sur l'année précédente (créée au besoin), en ne
 * touchant qu'aux inscriptions issues du backfill — reconnaissables à leur
 * enrolled_by_user_id nul, puisque toute inscription saisie dans
 * l'application porte l'identifiant de son auteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $activeYear = DB::table('academic_years')->where('is_active', true)->first();

        if (! $activeYear) {
            return;
        }

        $backfilled = DB::table('student_enrollments')
            ->where('academic_year_id', $activeYear->id)
            ->whereNull('enrolled_by_user_id')
            ->pluck('student_id');

        if ($backfilled->isEmpty()) {
            return;
        }

        $previousYearId = $this->previousYearId($activeYear);

        if (! $previousYearId) {
            return;
        }

        DB::table('student_enrollments')
            ->where('academic_year_id', $activeYear->id)
            ->whereNull('enrolled_by_user_id')
            ->update(['academic_year_id' => $previousYearId]);

        DB::table('students')
            ->whereIn('id', $backfilled)
            ->where('academic_year_id', $activeYear->id)
            ->update(['academic_year_id' => $previousYearId]);

        // Les encaissements de ces élèves concernaient l'année écoulée : ils
        // doivent la suivre, sinon leur reste dû ne serait plus imputé à la
        // bonne année lors de la clôture.
        DB::table('payments')
            ->whereIn('student_id', $backfilled)
            ->where('academic_year_id', $activeYear->id)
            ->update(['academic_year_id' => $previousYearId]);
    }

    /**
     * Année précédant l'année active : celle dont le code est immédiatement
     * inférieur, sinon celle déduite du format AAAA-BBBB, créée au besoin.
     */
    private function previousYearId(object $activeYear): ?int
    {
        $existing = DB::table('academic_years')
            ->where('code', '<', $activeYear->code)
            ->orderByDesc('code')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        if (! preg_match('/^(\d{4})-(\d{4})$/', $activeYear->code, $parts)) {
            return null;
        }

        $code = ((int) $parts[1] - 1).'-'.$parts[1];

        return (int) DB::table('academic_years')->insertGetId([
            'code' => $code,
            'label' => "Annee {$code}",
            'is_active' => false,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Irréversible : on ne saurait pas distinguer ces lignes de celles
        // légitimement saisies sur l'année précédente depuis.
    }
};
