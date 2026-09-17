<?php

namespace Modules\Payments\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Models\Payment;

/**
 * Un parent peut verser moins que le montant d'une ligne : c'est un acompte.
 * La ligne n'est alors pas soldée, et le reçu doit dire combien a déjà été
 * versé et combien il reste à payer.
 *
 * Calcule, pour chaque ligne d'un paiement, le cumul versé sur cette ligne
 * jusqu'à ce paiement inclus (les paiements suivants n'entrent pas en
 * compte : un reçu réimprimé montre la situation au jour où il a été émis).
 */
class PaymentLineBalances
{
    /** @param  iterable<Payment>  $payments  avec la relation "items" chargée */
    public static function attach(iterable $payments): void
    {
        $payments = collect($payments);

        if ($payments->isEmpty()) {
            return;
        }

        $history = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->whereIn('payments.student_id', $payments->pluck('student_id')->unique()->values()->all())
            ->whereNull('payments.deleted_at')
            ->orderBy('payments.id')
            ->get(['payments.id as payment_id', 'payments.student_id', 'payment_items.item_type', 'payment_items.tuition_installment_id', 'payment_items.fee_type_id', 'payment_items.paid_amount'])
            ->groupBy(fn ($row) => self::lineKey($row->student_id, $row->item_type, $row->tuition_installment_id, $row->fee_type_id));

        foreach ($payments as $payment) {
            $isPartial = false;

            foreach ($payment->items as $item) {
                $lines = $history->get(self::lineKey($payment->student_id, $item->item_type, $item->tuition_installment_id, $item->fee_type_id), collect());
                $paidToDate = (float) $lines->where('payment_id', '<=', $payment->id)->sum('paid_amount');
                $remaining = round(max((float) $item->expected_amount - $paidToDate, 0), 2);

                $item->setAttribute('paid_to_date', round($paidToDate, 2));
                $item->setAttribute('remaining_after', $remaining);
                $item->setAttribute('line_status', $remaining > 0 ? 'partial' : 'settled');
                $item->setAttribute('label', $item->label());

                $isPartial = $isPartial || $remaining > 0;
            }

            $payment->setAttribute('is_partial', $isPartial);
        }
    }

    private static function lineKey($studentId, $type, $installmentId, $feeTypeId): string
    {
        return implode('|', [(int) $studentId, $type, (int) $installmentId, (int) $feeTypeId]);
    }
}
