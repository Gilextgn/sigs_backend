<?php

namespace Modules\Students\Services;

use Illuminate\Support\Facades\DB;
use Modules\Students\Models\Student;

/**
 * Génère le matricule côté serveur (recommandation README §6) :
 * format ELV-AAAA-000001, avec verrou pessimiste pour garantir l'unicité
 * même en cas d'inscriptions concurrentes (remplace le trigger MySQL
 * trg_students_before_insert de l'ancien schéma).
 */
class MatriculeGenerator
{
    public function generate(?int $year = null): array
    {
        $year ??= (int) date('Y');
        $prefix = config('school.matricule_prefix', 'ELV');

        return DB::transaction(function () use ($year, $prefix) {
            $lastSequence = Student::where('registration_year', $year)
                ->lockForUpdate()
                ->max('registration_sequence') ?? 0;

            $sequence = $lastSequence + 1;
            $matricule = sprintf('%s-%d-%06d', $prefix, $year, $sequence);

            return [
                'registration_year' => $year,
                'registration_sequence' => $sequence,
                'matricule' => $matricule,
            ];
        });
    }
}
