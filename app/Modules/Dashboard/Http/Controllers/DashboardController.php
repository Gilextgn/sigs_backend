<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Models\Payment;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Students\Models\Student;
use Modules\Teachers\Models\Teacher;

class DashboardController extends Controller
{
    public function summary()
    {
        $studentsCount = Student::where('status', 'active')->count();
        $classesCount = SchoolClass::where('is_active', true)->count();
        $teachersCount = Teacher::where('status', 'active')->count();

        $totalCollected = (float) Payment::whereNull('deleted_at')->sum('total_paid_amount');
        $theoreticalTotal = (float) SchoolClass::where('is_active', true)
            ->join('students', 'students.class_id', '=', 'classes.id')
            ->where('students.status', 'active')
            ->sum('classes.tuition_amount');

        $outstanding = max($theoreticalTotal - $totalCollected, 0);
        $recoveryRate = $theoreticalTotal > 0 ? round(($totalCollected / $theoreticalTotal) * 100, 1) : 0;

        $debtorsCount = DB::table('students')
            ->join('classes', 'classes.id', '=', 'students.class_id')
            ->leftJoin(DB::raw('(select student_id, sum(total_paid_amount) as paid from payments where deleted_at is null group by student_id) as p'), 'p.student_id', '=', 'students.id')
            ->where('students.status', 'active')
            ->whereRaw('classes.tuition_amount > COALESCE(p.paid, 0)')
            ->count();

        return response()->json([
            'students' => $studentsCount,
            'classes' => $classesCount,
            'teachers' => $teachersCount,
            'total_collected' => round($totalCollected, 2),
            'outstanding' => round($outstanding, 2),
            'debtors' => $debtorsCount,
            'recovery_rate' => $recoveryRate,
        ]);
    }

    public function cycleBreakdown()
    {
        return DB::table('students')
            ->join('classes', 'classes.id', '=', 'students.class_id')
            ->join('school_cycles', 'school_cycles.id', '=', 'classes.cycle_id')
            ->where('students.status', 'active')
            ->selectRaw('school_cycles.label as cycle, count(*) as total')
            ->groupBy('school_cycles.label')
            ->orderByDesc('total')
            ->get();
    }

    public function recentPayments()
    {
        return Payment::with('student:id,matricule,first_name,last_name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    public function topDebtors()
    {
        return app(\Modules\Debtors\Http\Controllers\DebtorController::class)
            ->index(request())
            ->getData();
    }

    public function statistics(\Illuminate\Http\Request $request)
    {
        $days = min(max($request->integer('days', 30), 7), 365);
        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();
        $previousStart = (clone $start)->subDays($days);
        $previousEnd = (clone $start)->subSecond();

        $dailyRows = Payment::query()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('payment_date as date, SUM(total_paid_amount) as amount, COUNT(*) as payment_count')
            ->groupBy('payment_date')
            ->orderBy('payment_date')
            ->get()
            ->keyBy('date');

        $daily = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $dailyRows->get($key);
            $daily->push([
                'date' => $key,
                'amount' => round((float) ($row->amount ?? 0), 2),
                'payment_count' => (int) ($row->payment_count ?? 0),
            ]);
        }

        $periodTotal = (float) Payment::whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])->sum('total_paid_amount');
        $previousTotal = (float) Payment::whereBetween('payment_date', [$previousStart->toDateString(), $previousEnd->toDateString()])->sum('total_paid_amount');

        return response()->json([
            'period_total' => round($periodTotal, 2),
            'period_payment_count' => Payment::whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])->count(),
            'daily_average' => round($periodTotal / $days, 2),
            'comparison' => [
                'previous_total' => round($previousTotal, 2),
                'change_percent' => $previousTotal > 0 ? round((($periodTotal - $previousTotal) / $previousTotal) * 100, 1) : ($periodTotal > 0 ? 100 : 0),
            ],
            'daily' => $daily,
        ]);
    }
}
