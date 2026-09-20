<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollEntry;
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

    private function calculateHourlyPay(int $teacherId, string $period): array
    {
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
            $hourlyRate = (float) ($session->assignment?->hourly_rate ?? 0);

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

            $paidMinutes = match ($attendance->status) {
                'present', 'justified' => max(0, $plannedMinutes - $absenceMinutes),
                default => 0,
            };

            $minutes += $paidMinutes;
            $amount += ($paidMinutes / 60) * $hourlyRate;
        }

        return [
            'worked_minutes' => (int) $minutes,
            'worked_hours' => round($minutes / 60, 2),
            'amount' => round($amount, 2),
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
                    'present', 'justified' => max(0, $plannedMinutes - $absenceMinutes),
                    'replaced' => $plannedMinutes,
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

    public function markPaid(PayrollEntry $payrollEntry)
    {
        $payrollEntry->update(['status' => 'paid', 'paid_at' => now()]);

        return $payrollEntry->fresh();
    }
}
