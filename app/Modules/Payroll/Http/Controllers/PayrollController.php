<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollEntry;
use Modules\Teachers\Models\Teacher;
use Modules\Teachers\Models\TeachingSession;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        return PayrollEntry::with('teacher:id,full_name,subject')
            ->when($request->period, fn ($q, $p) => $q->where('period', $p))
            ->orderByDesc('period')
            ->paginate($request->integer('per_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => ['required', \App\Support\SchoolRule::exists('teachers')],
            'period' => ['required', 'string', 'size:7'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $calculation = $this->calculateHourlyPay($data['teacher_id'], $data['period']);
        $data['base_amount'] = $calculation['amount'];

        return PayrollEntry::create($data);
    }

    public function estimate(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => ['required', \App\Support\SchoolRule::exists('teachers')],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        return response()->json($this->calculateHourlyPay($data['teacher_id'], $data['period']));
    }

    /**
     * Base de paie du mois : salaire fixe pour un enseignant au mois, sinon
     * les heures réellement faites d'après les présences.
     */
    private function calculateHourlyPay(int $teacherId, string $period): array
    {
        $teacher = Teacher::find($teacherId);
        $startDate = $period.'-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $sessions = TeachingSession::with(['assignment', 'attendance'])
            ->where(function ($query) use ($teacherId) {
                $query->whereHas('assignment', fn ($assignmentQuery) => $assignmentQuery->where('teacher_id', $teacherId))
                    ->orWhereHas('attendance', fn ($attendanceQuery) => $attendanceQuery->where('replacement_teacher_id', $teacherId));
            })
            ->where('status', 'completed')
            ->whereBetween('session_date', [$startDate, $endDate])
            ->get();

        $minutes = 0;
        $amount = 0.0;

        foreach ($sessions as $session) {
            $attendance = $session->attendance;
            $plannedMinutes = (int) $session->planned_minutes;
            $hourlyRate = $this->rateFor($session, $teacher);

            if (! $attendance) {
                continue;
            }

            if ((int) ($attendance->replacement_teacher_id ?? 0) === $teacherId) {
                $paidMinutes = $plannedMinutes;
                $minutes += $paidMinutes;
                $amount += ($paidMinutes / 60) * $hourlyRate;
                continue;
            }

            if ((int) $session->assignment->teacher_id !== $teacherId) {
                continue;
            }

            $absenceMinutes = min((int) ($attendance->absence_minutes ?? 0), $plannedMinutes);

            // Retard : les minutes manquées sont déduites.
            $paidMinutes = match ($attendance->status) {
                'present', 'justified', 'late' => max(0, $plannedMinutes - $absenceMinutes),
                default => 0,
            };

            $minutes += $paidMinutes;
            $amount += ($paidMinutes / 60) * $hourlyRate;
        }

        $paidMonthly = $teacher && ! $teacher->isPaidHourly();

        return [
            'pay_mode' => $paidMonthly ? 'monthly' : 'hourly',
            'worked_minutes' => (int) $minutes,
            'worked_hours' => round($minutes / 60, 2),
            // Les heures restent affichées pour un salaire fixe : elles disent
            // ce qui a été fait, sans entrer dans le montant.
            'hourly_amount' => round($amount, 2),
            'amount' => $paidMonthly ? round((float) $teacher->monthly_salary, 2) : round($amount, 2),
        ];
    }

    public function show(PayrollEntry $payrollEntry)
    {
        $startDate = $payrollEntry->period.'-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $sessions = TeachingSession::with(['assignment.teacher:id,full_name', 'assignment.subject:id,label', 'schoolClass:id,label', 'attendance'])
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $payrollEntry->teacher_id))
            ->whereBetween('session_date', [$startDate, $endDate])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get()
            ->map(function ($session) {
                $attendance = $session->attendance;
                $plannedMinutes = (int) $session->planned_minutes;
                $absenceMinutes = (int) ($attendance?->absence_minutes ?? 0);

                $paidMinutes = match ($attendance?->status) {
                    'present', 'justified', 'late' => max(0, $plannedMinutes - $absenceMinutes),
                    // Remplacé : c'est le remplaçant qui est payé, pas le titulaire.
                    'replaced' => 0,
                    default => 0,
                };

                return [
                    'id' => $session->id,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'class_label' => $session->schoolClass?->label ?? '—',
                    'subject_label' => $session->assignment?->subject?->label ?? '—',
                    'planned_minutes' => $plannedMinutes,
                    'paid_minutes' => $paidMinutes,
                    'status' => $attendance?->status ?? 'not_recorded',
                    'absence_minutes' => $absenceMinutes,
                    'reason' => $attendance?->reason,
                ];
            });

        return response()->json([
            'id' => $payrollEntry->id,
            'period' => $payrollEntry->period,
            'teacher' => $payrollEntry->teacher ? ['id' => $payrollEntry->teacher->id, 'full_name' => $payrollEntry->teacher->full_name] : null,
            'base_amount' => (float) $payrollEntry->base_amount,
            'bonus_amount' => (float) $payrollEntry->bonus_amount,
            'deduction_amount' => (float) $payrollEntry->deduction_amount,
            'status' => $payrollEntry->status,
            'paid_at' => $payrollEntry->paid_at,
            'net_amount' => $payrollEntry->netAmount(),
            'sessions' => $sessions,
        ]);
    }

    /**
     * Tarif d'une séance : celui de l'affectation, sinon celui saisi sur la fiche
     * de l'enseignant payé (le remplaçant est payé à son propre tarif).
     */
    private function rateFor(TeachingSession $session, ?Teacher $teacher): float
    {
        if ($teacher && (int) $session->assignment?->teacher_id !== $teacher->id) {
            return (float) ($teacher->hourly_rate ?: $session->assignment?->hourly_rate ?: 0);
        }

        return (float) ($session->assignment?->hourly_rate ?: $teacher?->hourly_rate ?: 0);
    }

    /**
     * Récapitulatif d'une année scolaire pour un enseignant (septembre → août) :
     * heures faites et paie de chaque mois, totaux. Chaque mois repart de zéro.
     */
    public function annualSummary(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => ['required', \App\Support\SchoolRule::exists('teachers')],
            'start_year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $teacher = Teacher::findOrFail($data['teacher_id']);
        $start = (int) $data['start_year'];
        $entries = PayrollEntry::where('teacher_id', $teacher->id)
            ->whereBetween('period', [sprintf('%d-09', $start), sprintf('%d-08', $start + 1)])
            ->get()->keyBy('period');

        $months = collect([9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8])->map(function (int $month) use ($start, $teacher, $entries) {
            $period = sprintf('%d-%02d', $month >= 9 ? $start : $start + 1, $month);
            $worked = $this->calculateHourlyPay($teacher->id, $period);
            $entry = $entries->get($period);

            return [
                'period' => $period,
                'worked_hours' => $worked['worked_hours'],
                'base_amount' => $entry ? (float) $entry->base_amount : null,
                'bonus_amount' => $entry ? (float) $entry->bonus_amount : null,
                'deduction_amount' => $entry ? (float) $entry->deduction_amount : null,
                'net_amount' => $entry?->netAmount(),
                'status' => $entry?->status ?? 'not_generated',
                'paid_at' => $entry?->paid_at?->toDateString(),
            ];
        })->filter(fn ($row) => $row['worked_hours'] > 0 || $row['net_amount'] !== null)->values();

        return response()->json([
            'teacher' => ['id' => $teacher->id, 'full_name' => $teacher->full_name, 'pay_mode' => $teacher->pay_mode ?? 'hourly'],
            'school_year' => $start.'-'.($start + 1),
            'months' => $months,
            'totals' => [
                'worked_hours' => round($months->sum('worked_hours'), 2),
                'net_amount' => round($months->sum('net_amount'), 2),
                'paid_amount' => round($months->where('status', 'paid')->sum('net_amount'), 2),
                'pending_amount' => round($months->where('status', 'pending')->sum('net_amount'), 2),
            ],
        ]);
    }

    public function markPaid(PayrollEntry $payrollEntry)
    {
        $payrollEntry->update(['status' => 'paid', 'paid_at' => now()]);

        return $payrollEntry->fresh();
    }
}
