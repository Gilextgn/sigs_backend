<?php

namespace Modules\Payments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Fees\Models\FeeType;
use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentItem;
use Modules\Tranches\Models\TuitionInstallment;
use Modules\Security\Models\AuditLog;

/**
 * Reprend les règles des triggers SQL trg_payment_items_before_insert /
 * trg_payments_after_insert : pas de dépassement du montant dû par ligne,
 * référence unique, journalisation. Le paiement n'est jamais modifiable
 * après création (seulement consultable, imprimable ou supprimé).
 */
class PaymentService
{
    public function create(int $studentId, array $items, int $cashierUserId): Payment
    {
        return DB::transaction(function () use ($studentId, $items, $cashierUserId) {
            $enrichedItems = [];
            $total = 0.0;
            $itemKeys = [];

            foreach ($items as $item) {
                $itemKey = $item['item_type'].'-'.($item['tuition_installment_id'] ?? $item['fee_type_id'] ?? '');
                if (isset($itemKeys[$itemKey])) {
                    throw ValidationException::withMessages([
                        'items' => 'Une même ligne ne peut pas être encaissée deux fois dans le même paiement.',
                    ]);
                }
                $itemKeys[$itemKey] = true;
                $enriched = $this->validateAndPriceItem($studentId, $item);
                $enrichedItems[] = $enriched;
                $total += $enriched['paid_amount'];
            }

            $payment = Payment::create([
                'reference_code' => $this->generateReference(),
                'student_id' => $studentId,
                'cashier_user_id' => $cashierUserId,
                'payment_date' => now()->toDateString(),
                'total_paid_amount' => $total,
                'school_id' => 1,
                'created_at' => now(),
            ]);

            foreach ($enrichedItems as $item) {
                PaymentItem::create([...$item, 'payment_id' => $payment->id, 'created_at' => now()]);
            }

            $payment->load('items.tuitionInstallment', 'items.feeType', 'student.schoolClass', 'cashier');
            AuditLog::record('payment.created', 'Payment', (string) $payment->id, [
                'reference_code' => $payment->reference_code,
                'student_id' => $studentId,
                'total_paid_amount' => $total,
            ]);

            $payment->remaining_amount = $this->studentRemainingAmount($studentId);

            return $payment;
        });
    }

    private function validateAndPriceItem(int $studentId, array $item): array
    {
        if ($item['item_type'] === 'TRANCHE') {
            $installment = TuitionInstallment::lockForUpdate()->findOrFail($item['tuition_installment_id']);
            $student = \Modules\Students\Models\Student::findOrFail($studentId);
            abort_unless($student->class_id === $installment->class_id, 422, 'Cette tranche n’appartient pas à la classe de l’élève.');
            $referenceAmount = (float) $installment->amount;

            $alreadyPaid = (float) PaymentItem::query()
                ->whereHas('payment', fn ($q) => $q->where('student_id', $studentId)->whereNull('deleted_at'))
                ->where('item_type', 'TRANCHE')
                ->where('tuition_installment_id', $installment->id)
                ->sum('paid_amount');

            $key = 'tuition_installment_id';
            $refId = $installment->id;
        } else {
            $fee = FeeType::lockForUpdate()->findOrFail($item['fee_type_id']);
            $referenceAmount = (float) $fee->amount;

            $alreadyPaid = (float) PaymentItem::query()
                ->whereHas('payment', fn ($q) => $q->where('student_id', $studentId)->whereNull('deleted_at'))
                ->where('item_type', 'AUTRE_FRAIS')
                ->where('fee_type_id', $fee->id)
                ->sum('paid_amount');

            $key = 'fee_type_id';
            $refId = $fee->id;
        }

        $paidAmount = (float) $item['paid_amount'];

        if (($alreadyPaid + $paidAmount) > $referenceAmount) {
            $itemLabel = $item['item_type'] === 'TRANCHE' ? $installment->label : $fee->label;
            $remaining = max($referenceAmount - $alreadyPaid, 0);
            throw ValidationException::withMessages([
                'items' => "La ligne « {$itemLabel} » dépasse le reste autorisé de ".number_format($remaining, 2, ',', ' ')." XOF.",
            ]);
        }

        return [
            'item_type' => $item['item_type'],
            'tuition_installment_id' => $key === 'tuition_installment_id' ? $refId : null,
            'fee_type_id' => $key === 'fee_type_id' ? $refId : null,
            'expected_amount' => $referenceAmount,
            'paid_amount' => $paidAmount,
        ];
    }

    private function generateReference(): string
    {
        $date = now()->toDateString();
        $sequence = DB::table('payment_sequences')->where('sequence_date', $date)->lockForUpdate()->first();

        if ($sequence) {
            $number = $sequence->last_number + 1;
            DB::table('payment_sequences')->where('sequence_date', $date)->update(['last_number' => $number]);
        } else {
            $number = 1;
            DB::table('payment_sequences')->insert(['sequence_date' => $date, 'last_number' => $number]);
        }

        return 'PAY-'.str_replace('-', '', $date).'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function studentRemainingAmount(int $studentId): float
    {
        $student = \Modules\Students\Models\Student::with('schoolClass')->findOrFail($studentId);
        $tuition = (float) ($student->schoolClass?->tuition_amount ?? 0);
        $paidTuition = (float) PaymentItem::whereHas('payment', fn ($q) => $q->where('student_id', $studentId)->whereNull('deleted_at'))
            ->where('item_type', 'TRANCHE')
            ->sum('paid_amount');

        return round(max($tuition - $paidTuition, 0), 2);
    }
}
