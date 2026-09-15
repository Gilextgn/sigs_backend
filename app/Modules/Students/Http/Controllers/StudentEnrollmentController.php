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
        $currentYear = $student->academicYear;
        $nextYear = $currentYear
            ? AcademicYear::where('code', '>', $currentYear->code)->orderBy('code')->first()
            : null;

        $debts = $this->closedYearDebts($student);
        $alreadyEnrolled = $nextYear && StudentEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $nextYear->id)
            ->exists();

        $expectedNextCode = $currentYear ? $this->followingYearCode($currentYear->code) : null;

        return response()->json([
            'current_year' => $currentYear ? ['id' => $currentYear->id, 'code' => $currentYear->code] : null,
            'target_year' => $nextYear ? ['id' => $nextYear->id, 'code' => $nextYear->code] : null,
            // Permet à l'écran de proposer la création de l'année manquante
            // plutôt que de renvoyer l'utilisateur chercher dans Paramètres.
            'expected_next_code' => $nextYear ? null : $expectedNextCode,
            'debts' => $debts,
            'blocked_reason' => match (true) {
                ! $currentYear => "Cet élève n'est rattaché à aucune année scolaire : corrigez sa fiche avant de le réinscrire.",
                ! $nextYear => "La réinscription vise l'année qui suit {$currentYear->code}, or elle n'existe pas encore.",
                (bool) $nextYear->closed_at => "L'année {$nextYear->code} est clôturée.",
                $alreadyEnrolled => "Cet élève est déjà inscrit pour l'année {$nextYear->code}.",
                $debts->isNotEmpty() => "Réinscription bloquée : le solde d'une année clôturée n'est pas réglé.",
                default => null,
            },
        ]);
    }

    public function store(ReEnrollStudentRequest $request, Student $student)
    {
        $targetYear = $this->nextYearFor($student);

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
     * La réinscription vise toujours l'année qui SUIT celle de l'élève, et
     * non l'année active : une école réinscrit couramment en juin, alors que
     * l'année en cours n'est ni close ni remplacée.
     *
     * Le classement se fait sur le code (format AAAA-AAAA) plutôt que sur
     * date_start, qui reste nul pour les années créées depuis l'écran
     * Paramètres (le formulaire n'envoie que le code).
     */
    private function nextYearFor(Student $student): AcademicYear
    {
        $currentYear = $student->academicYear;
        abort_if(! $currentYear, 422, "Cet élève n'est rattaché à aucune année scolaire : corrigez sa fiche avant de le réinscrire.");

        $nextYear = AcademicYear::where('code', '>', $currentYear->code)->orderBy('code')->first();

        abort_if(
            ! $nextYear,
            422,
            "La réinscription vise l'année qui suit {$currentYear->code}, or elle n'existe pas encore.",
        );
        abort_if($nextYear->closed_at, 422, "L'année {$nextYear->code} est clôturée.");

        return $nextYear;
    }

    /**
     * Code de l'année qui suit, déduit du format AAAA-BBBB (2026-2027 ->
     * 2027-2028). Null si le code ne suit pas ce format : on ne devine rien.
     */
    private function followingYearCode(string $code): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{4})$/', $code, $parts)) {
            return null;
        }

        return ((int) $parts[2]).'-'.((int) $parts[2] + 1);
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
