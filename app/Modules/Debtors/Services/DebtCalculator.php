<?php

namespace Modules\Debtors\Services;

use Illuminate\Support\Facades\DB;
use Modules\Fees\Models\FeeType;
use Modules\SchoolClasses\Models\SchoolClass;

/**
 * Reste-dû d'un élève pour une classe donnée, optionnellement scopé à une
 * année scolaire précise (via payments.academic_year_id) et/ou une tranche.
 *
 * Extrait de DebtorController : avec $academicYearId = null, reproduit
 * exactement le comportement historique de /api/debtors ("tous les
 * paiements jamais faits, classe actuelle"). Avec un $academicYearId
 * précis, sert au calcul de clôture d'année et au blocage de
 * réinscription — même logique, juste filtrée par année.
 */
class DebtCalculator
{
    /**
     * $preloadedClass évite une requête par élève quand l'appelant a déjà
     * chargé la classe (avec sa relation "installments") en amont, par
     * exemple via Student::with(['schoolClass.installments']) sur une liste.
     */
    public function calculate(int $studentId, int $classId, ?int $academicYearId = null, ?int $trancheId = null, ?SchoolClass $preloadedClass = null): array
    {
        $schoolClass = $preloadedClass ?? SchoolClass::with('installments')->find($classId);
        $theoretical = $this->theoreticalAmount($schoolClass, $trancheId);
        $paid = $this->paidAmount($studentId, $trancheId, $academicYearId);

        return [
            'theoretical_amount' => round($theoretical, 2),
            'paid_amount' => round($paid, 2),
            'outstanding_amount' => round(max($theoretical - $paid, 0), 2),
            'unpaid_items' => $this->unpaidItems($studentId, $classId, $academicYearId, $schoolClass),
        ];
    }

    private function theoreticalAmount(?SchoolClass $schoolClass, ?int $trancheId): float
    {
        if ($trancheId) {
            return (float) ($schoolClass?->installments->firstWhere('id', $trancheId)?->amount ?? 0);
        }

        return (float) ($schoolClass?->tuition_amount ?? 0);
    }

    private function paidAmount(int $studentId, ?int $trancheId, ?int $academicYearId): float
    {
        $query = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->where('payments.student_id', $studentId)
            ->whereNull('payments.deleted_at');

        if ($academicYearId !== null) {
            $query->where('payments.academic_year_id', $academicYearId);
        }

        if ($trancheId) {
            $query->where('payment_items.item_type', 'TRANCHE')
                ->where('payment_items.tuition_installment_id', $trancheId);
        } else {
            $query->where('payment_items.item_type', 'TRANCHE');
        }

        return (float) $query->sum('payment_items.paid_amount');
    }

    private function unpaidItems(int $studentId, int $classId, ?int $academicYearId, ?SchoolClass $preloadedClass = null): array
    {
        $paidByTrancheQuery = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->where('payments.student_id', $studentId)
            ->whereNull('payments.deleted_at')
            ->where('payment_items.item_type', 'TRANCHE');

        if ($academicYearId !== null) {
            $paidByTrancheQuery->where('payments.academic_year_id', $academicYearId);
        }

        $paidByTranche = $paidByTrancheQuery
            ->select('payment_items.tuition_installment_id', DB::raw('SUM(payment_items.paid_amount) as paid'))
            ->groupBy('payment_items.tuition_installment_id')
            ->pluck('paid', 'tuition_installment_id');

        $schoolClass = $preloadedClass ?? SchoolClass::with('installments')->find($classId);
        $installments = $schoolClass?->installments
            ->map(function ($installment) use ($paidByTranche) {
                $amount = (float) $installment->amount;
                $paid = (float) ($paidByTranche[$installment->id] ?? 0);

                return [
                    'type' => 'TRANCHE',
                    'label' => $installment->label,
                    'amount' => round($amount, 2),
                    'paid' => round($paid, 2),
                    'remaining' => round(max($amount - $paid, 0), 2),
                ];
            })
            ->filter(fn ($item) => $item['remaining'] > 0)
            ->values()
            ->all() ?? [];

        $paidByFeeQuery = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->where('payments.student_id', $studentId)
            ->whereNull('payments.deleted_at')
            ->where('payment_items.item_type', 'AUTRE_FRAIS');

        if ($academicYearId !== null) {
            $paidByFeeQuery->where('payments.academic_year_id', $academicYearId);
        }

        $paidByFee = $paidByFeeQuery
            ->select('payment_items.fee_type_id', DB::raw('SUM(payment_items.paid_amount) as paid'))
            ->groupBy('payment_items.fee_type_id')
            ->pluck('paid', 'fee_type_id');

        $fees = FeeType::where('is_active', true)
            ->where('is_mandatory', true)
            ->whereHas('classes', fn ($query) => $query->where('classes.id', $classId))
            ->get()
            ->map(function (FeeType $fee) use ($paidByFee) {
                $amount = (float) $fee->amount;
                $paid = (float) ($paidByFee[$fee->id] ?? 0);

                return [
                    'type' => 'AUTRE_FRAIS',
                    'label' => $fee->label,
                    'amount' => round($amount, 2),
                    'paid' => round($paid, 2),
                    'remaining' => round(max($amount - $paid, 0), 2),
                ];
            })
            ->filter(fn ($item) => $item['remaining'] > 0)
            ->values()
            ->all();

        return array_merge($installments, $fees);
    }
}
