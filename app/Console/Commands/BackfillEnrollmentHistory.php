<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentEnrollment;

/**
 * À rejouer manuellement une fois par environnement après le déploiement
 * de l'inscription/réinscription par année scolaire : rattache les élèves
 * et paiements existants (academic_year_id encore NULL) à l'année active,
 * et crée l'historique d'inscription correspondant.
 *
 * Best-effort : il n'existe aucun historique réel à reconstituer, tout
 * l'existant est rattaché à l'année active au moment de l'exécution.
 * Idempotente : ne touche que les lignes encore NULL, peut être rejouée.
 */
class BackfillEnrollmentHistory extends Command
{
    protected $signature = 'students:backfill-enrollment-history';

    protected $description = "Rattache les élèves et paiements existants à l'année scolaire active et crée leur historique d'inscription";

    public function handle(): int
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            $this->error('Aucune année scolaire active : activez-en une avant de lancer ce backfill.');

            return self::FAILURE;
        }

        $studentsBackfilled = 0;

        Student::whereNull('academic_year_id')->each(function (Student $student) use ($activeYear, &$studentsBackfilled) {
            $student->update(['academic_year_id' => $activeYear->id]);

            StudentEnrollment::updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $activeYear->id],
                ['class_id' => $student->class_id],
            );

            $studentsBackfilled++;
        });

        $paymentsBackfilled = Payment::whereNull('academic_year_id')
            ->whereHas('student', fn ($q) => $q->whereNotNull('academic_year_id'))
            ->get()
            ->each(function (Payment $payment) {
                $payment->update(['academic_year_id' => $payment->student->academic_year_id]);
            })
            ->count();

        $this->info("{$studentsBackfilled} élève(s) rattaché(s) à l'année {$activeYear->code}, {$paymentsBackfilled} paiement(s) rattaché(s).");

        return self::SUCCESS;
    }
}
