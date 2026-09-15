<?php

namespace Modules\Students\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\Debtors\Services\DebtCalculator;
use Modules\Security\Models\AuditLog;
use Modules\Students\Http\Requests\ReEnrollStudentRequest;
use Modules\Students\Http\Resources\StudentResource;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentEnrollment;

class StudentEnrollmentController extends Controller
{
    public function __construct(private DebtCalculator $debtCalculator)
    {
    }

    public function index(Student $student)
    {
        return $student->enrollments()
            ->with('academicYear', 'schoolClass')
            ->orderByDesc('academic_year_id')
            ->get()
            ->map(fn (StudentEnrollment $enrollment) => [
                'academic_year' => ['id' => $enrollment->academicYear->id, 'code' => $enrollment->academicYear->code],
                'class' => ['id' => $enrollment->schoolClass->id, 'label' => $enrollment->schoolClass->label],
            ]);
    }

    /**
     * Tout ce que l'écran de réinscription doit savoir avant la saisie :
     * l'année visée, et le cas échéant la raison qui bloque. Évite de
     * laisser l'utilisateur remplir le formulaire pour rien.
     */
    public function context(Student $student)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $lastYear = $student->academicYear;

        $debts = $this->closedYearDebts($student);
        $alreadyEnrolled = $activeYear && StudentEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $activeYear->id)
            ->exists();

        return response()->json([
            'last_year' => $lastYear ? ['id' => $lastYear->id, 'code' => $lastYear->code] : null,
            'target_year' => $activeYear ? ['id' => $activeYear->id, 'code' => $activeYear->code] : null,
            'debts' => $debts,
            'blocked_reason' => match (true) {
                ! $activeYear => "Aucune année scolaire active : activez l'année en cours dans Paramètres.",
                (bool) $activeYear->closed_at => "L'année {$activeYear->code} est clôturée : rouvrez-la pour pouvoir réinscrire.",
                $alreadyEnrolled => "Cet élève est déjà inscrit pour l'année {$activeYear->code}.",
                $debts->isNotEmpty() => "Réinscription bloquée : le solde d'une année clôturée n'est pas réglé.",
                default => null,
            },
        ]);
    }

    public function store(ReEnrollStudentRequest $request, Student $student)
    {
        $targetYear = $this->activeYear();

        abort_if(
            StudentEnrollment::where('student_id', $student->id)->where('academic_year_id', $targetYear->id)->exists(),
            422,
            "Cet élève est déjà inscrit pour l'année {$targetYear->code}.",
        );

        // Blocage strict : aucune réinscription tant qu'une année clôturée
        // reste impayée. Le seul moyen de débloquer est d'encaisser le solde,
        // ce qui retire automatiquement l'élève de la liste (calcul en direct).
        $closedDebts = $this->closedYearDebts($student);

        if ($closedDebts->isNotEmpty()) {
            return response()->json([
                'message' => 'Cet élève a des dettes sur une année scolaire clôturée : sa réinscription est bloquée tant que le solde n\'est pas réglé.',
                'debts' => $closedDebts,
            ], 422);
        }

        $classId = $request->integer('class_id');

        $enrollment = DB::transaction(function () use ($student, $targetYear, $classId, $request) {
            $enrollment = StudentEnrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $targetYear->id,
                'class_id' => $classId,
                'enrolled_by_user_id' => $request->user()->id,
            ]);

            $student->update([
                'class_id' => $classId,
                'academic_year_id' => $targetYear->id,
                // Réinscrire un élève transféré/archivé le réactive implicitement.
                'status' => 'active',
            ]);

            return $enrollment;
        });

        AuditLog::record('student.reenrolled', 'Student', (string) $student->id, [
            'academic_year_id' => $targetYear->id,
            'class_id' => $enrollment->class_id,
        ]);

        return new StudentResource($student->fresh('schoolClass', 'guardian', 'academicYear'));
    }

    /**
     * Réinscrire, c'est rattacher l'élève à l'année que l'école a déclarée
     * active — celle pour laquelle elle inscrit en ce moment. Une seule
     * année « en cours » à la fois, tout s'y rattache : pour préparer la
     * rentrée suivante, on active la nouvelle année puis on réinscrit.
     */
    private function activeYear(): AcademicYear
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        abort_if(! $activeYear, 422, "Aucune année scolaire active : activez l'année en cours dans Paramètres.");
        abort_if($activeYear->closed_at, 422, "L'année {$activeYear->code} est clôturée : rouvrez-la pour pouvoir réinscrire.");

        return $activeYear;
    }

    /**
     * Dettes de l'élève sur ses années scolaires déjà clôturées, tous
     * soldes positifs confondus. Recalculé en direct (pas de statut figé) :
     * un paiement fait après la clôture débloque automatiquement l'élève.
     */
    private function closedYearDebts(Student $student)
    {
        return $student->enrollments()
            ->whereHas('academicYear', fn ($q) => $q->whereNotNull('closed_at'))
            ->with('academicYear')
            ->get()
            ->map(fn (StudentEnrollment $enrollment) => [
                'academic_year' => $enrollment->academicYear->code,
                ...$this->debtCalculator->calculate($student->id, $enrollment->class_id, $enrollment->academic_year_id),
            ])
            ->filter(fn ($row) => $row['outstanding_amount'] > 0)
            ->values();
    }
}
