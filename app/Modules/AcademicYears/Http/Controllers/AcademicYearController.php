<?php

namespace Modules\AcademicYears\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\Debtors\Services\DebtCalculator;
use Modules\Security\Models\AuditLog;
use Modules\Students\Models\StudentEnrollment;

class AcademicYearController extends Controller
{
    public function __construct(private DebtCalculator $debtCalculator)
    {
    }

    public function index()
    {
        return AcademicYear::orderByDesc('date_start')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', \App\Support\SchoolRule::unique('academic_years', 'code')],
            'label' => ['required', 'string', 'max:60'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after:date_start'],
            'is_active' => ['boolean'],
        ]);

        return DB::transaction(function () use ($data) {
            if (! empty($data['is_active'])) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
            }

            return AcademicYear::create($data);
        });
    }

    public function activate(AcademicYear $academicYear)
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return $academicYear->fresh();
    }

    /**
     * Liste des élèves inscrits cette année-là dont le solde est encore
     * positif — recalculée en direct, jamais stockée (cf. close()).
     */
    /** L'année en cours, affichée en permanence dans la barre du haut. */
    public function active()
    {
        $year = AcademicYear::where('is_active', true)->first();

        return response()->json($year ? ['id' => $year->id, 'code' => $year->code, 'closed_at' => $year->closed_at] : null);
    }

    public function closingPreview(AcademicYear $academicYear)
    {
        return response()->json($this->computeYearDebtors($academicYear));
    }

    public function close(AcademicYear $academicYear)
    {
        abort_if($academicYear->closed_at, 422, 'Cette année est déjà clôturée.');

        // Clôturer en cours d'année bloquerait la réinscription de tous les
        // élèves qui n'ont pas encore tout payé — ce qui est normal en pleine année.
        abort_unless(
            $academicYear->isClosableNow(),
            422,
            "L'année {$academicYear->code} est encore en cours : la clôture sera possible à partir du "
                .$academicYear->closableFrom()->locale('fr')->isoFormat('D MMMM YYYY').'.',
        );

        DB::transaction(function () use ($academicYear) {
            $academicYear->update(['closed_at' => now(), 'closed_by_user_id' => request()->user()?->id]);
        });

        AuditLog::record('academic_year.closed', 'AcademicYear', (string) $academicYear->id, ['code' => $academicYear->code]);

        return response()->json([
            'academic_year' => $academicYear->fresh(),
            'debtors' => $this->computeYearDebtors($academicYear),
        ]);
    }

    public function reopen(AcademicYear $academicYear)
    {
        abort_unless($academicYear->closed_at, 422, "Cette année n'est pas clôturée.");

        $academicYear->update(['closed_at' => null, 'closed_by_user_id' => null]);

        AuditLog::record('academic_year.reopened', 'AcademicYear', (string) $academicYear->id, ['code' => $academicYear->code]);

        return $academicYear->fresh();
    }

    /**
     * Un débiteur par ligne d'inscription de l'année dont le reste-dû
     * (calculé sur la classe et l'année de CETTE inscription, pas la
     * classe actuelle de l'élève) est strictement positif.
     */
    private function computeYearDebtors(AcademicYear $academicYear): array
    {
        $enrollments = StudentEnrollment::with(['student', 'schoolClass.installments'])
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $debts = $this->debtCalculator->calculateMany(
            $enrollments->map(fn (StudentEnrollment $enrollment) => [
                'student_id' => $enrollment->student_id,
                'class_id' => $enrollment->class_id,
                'class' => $enrollment->schoolClass,
            ]),
            $academicYear->id,
        );

        return $enrollments
            ->map(fn (StudentEnrollment $enrollment) => [
                'student_id' => $enrollment->student_id,
                'matricule' => $enrollment->student->matricule,
                'full_name' => $enrollment->student->fullName(),
                ...$debts[$enrollment->student_id],
            ])
            ->filter(fn ($row) => $row['outstanding_amount'] > 0)
            ->sortByDesc('outstanding_amount')
            ->values()
            ->all();
    }
}
