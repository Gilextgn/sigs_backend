<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Debtors\Http\Controllers\DebtorController;
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

        // Même base que la liste des débiteurs : l'accueil, la barre du
        // haut et l'écran Débiteurs affichent des chiffres qui se recoupent.
        $debtors = app(DebtorController::class)->debtors();
        $theoreticalTotal = (float) SchoolClass::where('is_active', true)
            ->join('students', 'students.class_id', '=', 'classes.id')
            ->where('students.status', 'active')
            ->sum('classes.tuition_amount');

        $outstanding = (float) $debtors->sum('outstanding_amount');
        $debtorsCount = $debtors->count();
        $recoveryRate = $theoreticalTotal > 0 ? round((($theoreticalTotal - $outstanding) / $theoreticalTotal) * 100, 1) : 0;

        $monthStart = now()->startOfMonth();
        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $sameDayPreviousMonth = now()->subMonthNoOverflow()->endOfDay();
        $between = fn ($from, $to) => Payment::whereBetween('payment_date', self::dayBounds($from, $to));

        // Mois en cours comparé au mois précédent à la même date : comparer
        // le 12 septembre au mois d'août entier ferait toujours paraître
        // septembre en recul.
        $monthTotal = (float) $between($monthStart, now())->sum('total_paid_amount');
        $previousMonthTotal = (float) $between($previousMonthStart, $sameDayPreviousMonth)->sum('total_paid_amount');

        return response()->json([
            'students' => $studentsCount,
            'classes' => $classesCount,
            'teachers' => $teachersCount,
            'total_collected' => round($totalCollected, 2),
            'outstanding' => round($outstanding, 2),
            'debtors' => $debtorsCount,
            'recovery_rate' => $recoveryRate,
            'theoretical_total' => round($theoreticalTotal, 2),
            'month' => [
                'start' => $monthStart->toDateString(),
                'total' => round($monthTotal, 2),
                'payment_count' => $between($monthStart, now())->count(),
                'previous_total' => round($previousMonthTotal, 2),
                'change_percent' => $previousMonthTotal > 0
                    ? round((($monthTotal - $previousMonthTotal) / $previousMonthTotal) * 100, 1)
                    : null,
            ],
            'today' => [
                'total' => round((float) $between(now(), now())->sum('total_paid_amount'), 2),
                'payment_count' => $between(now(), now())->count(),
            ],
        ]);
    }

    public function recentPayments()
    {
        return Payment::with(['student:id,matricule,first_name,last_name', 'cashier:id,full_name'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    /**
     * Les plus gros reste-dû, avec le total et le nombre de débiteurs :
     * la barre du haut n'a besoin que de ça, pas de la liste entière.
     */
    public function topDebtors(Request $request)
    {
        $limit = min(max($request->integer('limit', 5), 1), 50);
        $debtors = app(DebtorController::class)->debtors();

        return response()->json([
            'total_outstanding' => round((float) $debtors->sum('outstanding_amount'), 2),
            'debtors_count' => $debtors->count(),
            'items' => $debtors->take($limit)->values(),
        ]);
    }

    public function statistics(Request $request)
    {
        $days = min(max($request->integer('days', 30), 7), 365);
        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();
        $previousStart = (clone $start)->subDays($days);
        $previousEnd = (clone $start)->subSecond();

        $dailyRows = Payment::query()
            ->whereBetween('payment_date', self::dayBounds($start, $end))
            ->selectRaw('DATE(payment_date) as date, SUM(total_paid_amount) as amount, COUNT(*) as payment_count')
            ->groupByRaw('DATE(payment_date)')
            ->orderByRaw('DATE(payment_date)')
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

        $periodTotal = (float) Payment::whereBetween('payment_date', self::dayBounds($start, $end))->sum('total_paid_amount');
        $previousTotal = (float) Payment::whereBetween('payment_date', self::dayBounds($previousStart, $previousEnd))->sum('total_paid_amount');

        return response()->json([
            'period_total' => round($periodTotal, 2),
            'period_payment_count' => Payment::whereBetween('payment_date', self::dayBounds($start, $end))->count(),
            'daily_average' => round($periodTotal / $days, 2),
            'comparison' => [
                'previous_total' => round($previousTotal, 2),
                'change_percent' => $previousTotal > 0 ? round((($periodTotal - $previousTotal) / $previousTotal) * 100, 1) : ($periodTotal > 0 ? 100 : 0),
            ],
            'daily' => $daily,
        ]);
    }

    /**
     * Bornes couvrant des journées entières. payment_date est une colonne
     * date, mais SQLite la stocke avec une heure (« 2026-09-17 00:00:00 ») :
     * comparer à « 2026-09-17 » exclurait les paiements du dernier jour.
     */
    private static function dayBounds($from, $to): array
    {
        return [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()];
    }
}
