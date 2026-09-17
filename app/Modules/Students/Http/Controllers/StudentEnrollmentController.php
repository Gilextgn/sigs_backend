<?php

namespace Modules\Students\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $this->enroll($student, $targetYear, $request->integer('class_id'), $request->user()->id);

        return new StudentResource($student->fresh('schoolClass', 'guardian', 'academicYear'));
    }

    /**
     * Ce que doit l'élève aujourd'hui, sur sa classe actuelle : même calcul
     * que la liste des débiteurs, lignes en acompte comprises.
     */
    public function balance(Student $student)
    {
        $student->load('schoolClass.installments');

        return response()->json($this->debtCalculator->calculate($student->id, $student->class_id, null, null, $student->schoolClass));
    }

    /**
     * Où en est la rentrée : les élèves de l'année écoulée, classés en
     * réinscrits, bloqués par une dette d'année clôturée, ou en attente.
     */
    public function progress()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            return response()->json(['active_year' => null, 'previous_year' => null, 'totals' => null, 'classes' => [], 'students' => []]);
        }

        $previousYear = AcademicYear::where('code', '<', $activeYear->code)->orderByDesc('code')->first();

        $previousEnrollments = $previousYear
            ? StudentEnrollment::with(['student.guardian', 'schoolClass.installments'])->where('academic_year_id', $previousYear->id)->get()
            : collect();

        $activeEnrollments = StudentEnrollment::where('academic_year_id', $activeYear->id)->get()->keyBy('student_id');

        // Dette de l'année écoulée pour tout le monde en une passe : affiche
        // ce que doit chaque famille, que la dette bloque ou non.
        $previousDebts = $previousYear
            ? $this->debtCalculator->calculateMany(
                $previousEnrollments->map(fn (StudentEnrollment $e) => ['student_id' => $e->student_id, 'class_id' => $e->class_id, 'class' => $e->schoolClass]),
                $previousYear->id,
            )
            : [];

        $pendingIds = $previousEnrollments->pluck('student_id')->reject(fn ($id) => $activeEnrollments->has($id))->values();
        $blockingDebts = $this->closedYearOutstandingByStudent($pendingIds);

        $students = $previousEnrollments->map(function (StudentEnrollment $enrollment) use ($activeEnrollments, $blockingDebts, $previousDebts) {
            $state = match (true) {
                $activeEnrollments->has($enrollment->student_id) => 're_enrolled',
                ($blockingDebts[$enrollment->student_id] ?? 0) > 0 => 'blocked',
                default => 'pending',
            };

            return [
                'student_id' => $enrollment->student_id,
                'matricule' => $enrollment->student->matricule,
                'full_name' => $enrollment->student->fullName(),
                'guardian' => $enrollment->student->guardian
                    ? ['full_name' => $enrollment->student->guardian->full_name, 'phone' => $enrollment->student->guardian->phone]
                    : null,
                'previous_class' => ['id' => $enrollment->class_id, 'label' => $enrollment->schoolClass?->label],
                'current_class_id' => $activeEnrollments->get($enrollment->student_id)?->class_id,
                'state' => $state,
                'previous_year_outstanding' => $previousDebts[$enrollment->student_id]['outstanding_amount'] ?? 0,
                'blocking_outstanding' => round((float) ($blockingDebts[$enrollment->student_id] ?? 0), 2),
            ];
        })->sortBy('full_name')->values();

        $count = fn ($rows, string $state) => $rows->where('state', $state)->count();

        $classes = $students->groupBy('previous_class.id')->map(fn ($rows) => [
            'class_id' => $rows->first()['previous_class']['id'],
            'label' => $rows->first()['previous_class']['label'],
            'expected' => $rows->count(),
            're_enrolled' => $count($rows, 're_enrolled'),
            'blocked' => $count($rows, 'blocked'),
            'pending' => $count($rows, 'pending'),
        ])->sortBy([['pending', 'desc'], ['label', 'asc']])->values();

        return response()->json([
            'active_year' => ['id' => $activeYear->id, 'code' => $activeYear->code, 'closed_at' => $activeYear->closed_at],
            'previous_year' => $previousYear ? ['id' => $previousYear->id, 'code' => $previousYear->code, 'closed_at' => $previousYear->closed_at] : null,
            'totals' => [
                'expected' => $students->count(),
                're_enrolled' => $count($students, 're_enrolled'),
                'blocked' => $count($students, 'blocked'),
                'pending' => $count($students, 'pending'),
                'new_students' => $activeEnrollments->keys()->diff($previousEnrollments->pluck('student_id'))->count(),
            ],
            'classes' => $classes,
            'students' => $students,
        ]);
    }

    /**
     * Réinscription en lot vers une même classe. Chaque élève passe par les
     * mêmes règles que la réinscription unitaire : un refus n'annule pas
     * les autres, il est rapporté avec sa raison.
     */
    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ]);

        $targetYear = $this->activeYear();
        $alreadyEnrolled = StudentEnrollment::where('academic_year_id', $targetYear->id)
            ->whereIn('student_id', $data['student_ids'])
            ->pluck('student_id')
            ->flip();
        $blockingDebts = $this->closedYearOutstandingByStudent(collect($data['student_ids']));

        $enrolled = [];
        $refused = [];

        foreach (Student::whereIn('id', $data['student_ids'])->get() as $student) {
            $reason = match (true) {
                $alreadyEnrolled->has($student->id) => "Déjà inscrit pour l'année {$targetYear->code}.",
                ($blockingDebts[$student->id] ?? 0) > 0 => "Bloqué : solde d'une année clôturée non réglé.",
                default => null,
            };

            if ($reason) {
                $refused[] = ['student_id' => $student->id, 'full_name' => $student->fullName(), 'reason' => $reason];

                continue;
            }

            $this->enroll($student, $targetYear, (int) $data['class_id'], $request->user()->id);
            $enrolled[] = $student->id;
        }

        return response()->json(['enrolled' => $enrolled, 'refused' => $refused]);
    }

    private function enroll(Student $student, AcademicYear $targetYear, int $classId, int $userId): void
    {
        DB::transaction(function () use ($student, $targetYear, $classId, $userId) {
            StudentEnrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $targetYear->id,
                'class_id' => $classId,
                'enrolled_by_user_id' => $userId,
            ]);

            $student->update([
                'class_id' => $classId,
                'academic_year_id' => $targetYear->id,
                // Réinscrire un élève transféré/archivé le réactive implicitement.
                'status' => 'active',
            ]);
        });

        AuditLog::record('student.reenrolled', 'Student', (string) $student->id, [
            'academic_year_id' => $targetYear->id,
            'class_id' => $classId,
        ]);
    }

    /**
     * Reste-dû cumulé sur les années clôturées pour une liste d'élèves, en
     * une passe par année clôturée (et non une par élève).
     *
     * @return array<int, float>
     */
    private function closedYearOutstandingByStudent(Collection $studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        $totals = [];

        StudentEnrollment::with('schoolClass.installments')
            ->whereIn('student_id', $studentIds->all())
            ->whereHas('academicYear', fn ($q) => $q->whereNotNull('closed_at'))
            ->get()
            ->groupBy('academic_year_id')
            ->each(function ($enrollments, $yearId) use (&$totals) {
                $debts = $this->debtCalculator->calculateMany(
                    $enrollments->map(fn (StudentEnrollment $e) => ['student_id' => $e->student_id, 'class_id' => $e->class_id, 'class' => $e->schoolClass]),
                    (int) $yearId,
                );

                foreach ($debts as $studentId => $debt) {
                    $totals[$studentId] = ($totals[$studentId] ?? 0) + $debt['outstanding_amount'];
                }
            });

        return $totals;
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
