<?php

namespace Modules\Payments\Services;

use Illuminate\Support\Collection;
use Modules\Payments\Models\CashClosing;
use Modules\Payments\Models\Payment;

/**
 * Le « point » : reprend le cahier des secrétaires (par classe, le nom de
 * l'élève et chaque versement) et y ajoute ce que le cahier ne montrait pas :
 * total par caissier, par jour, par ligne encaissée, les annulations (qui,
 * quand, pourquoi) et l'état des clôtures.
 */
class CashReport
{
    public function build(string $from, string $to, ?int $cashierId): array
    {
        $payments = Payment::with([
            'student:id,matricule,first_name,last_name,class_id',
            'student.schoolClass:id,label,sort_order',
            'cashier:id,full_name',
            'items.tuitionInstallment:id,label',
            'items.feeType:id,label',
        ])
            ->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->when($cashierId, fn ($q) => $q->where('cashier_user_id', $cashierId))
            ->orderBy('created_at')
            ->get();

        $cancellations = Payment::onlyTrashed()
            ->with(['student:id,first_name,last_name,class_id', 'student.schoolClass:id,label', 'cashier:id,full_name'])
            ->whereBetween('deleted_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($cashierId, fn ($q) => $q->where('cashier_user_id', $cashierId))
            ->orderBy('deleted_at')
            ->get();
        $deleters = \App\Models\User::whereIn('id', $cancellations->pluck('deleted_by_user_id')->filter()->unique())->pluck('full_name', 'id');

        $closings = CashClosing::with('cashier:id,full_name', 'reopenedBy:id,full_name')
            ->whereDate('closing_date', '>=', $from)->whereDate('closing_date', '<=', $to)
            ->when($cashierId, fn ($q) => $q->where('cashier_user_id', $cashierId))
            ->orderBy('closing_date')->orderBy('closed_at')
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'cashier_id' => $cashierId,
            'payment_count' => $payments->count(),
            'total_amount' => $this->sum($payments),
            'by_class' => $this->byClass($payments),
            'by_cashier' => $payments->groupBy('cashier_user_id')->map(fn (Collection $rows) => [
                'cashier_id' => $rows->first()->cashier_user_id,
                'full_name' => $rows->first()->cashier?->full_name,
                'payment_count' => $rows->count(),
                'total_amount' => $this->sum($rows),
            ])->values(),
            'by_day' => $payments->groupBy(fn ($p) => $p->payment_date->toDateString())->map(fn (Collection $rows, $date) => [
                'date' => $date,
                'payment_count' => $rows->count(),
                'total_amount' => $this->sum($rows),
            ])->values(),
            'by_line' => $payments->flatMap->items->groupBy(fn ($item) => $item->label())->map(fn (Collection $items, $label) => [
                'label' => $label,
                'total_amount' => round((float) $items->sum('paid_amount'), 2),
            ])->sortBy('label')->values(),
            'cancellations' => $cancellations->map(fn (Payment $p) => [
                'reference_code' => $p->reference_code,
                'payment_date' => $p->payment_date->toDateString(),
                'student' => $p->student ? $p->student->first_name.' '.$p->student->last_name : null,
                'class' => $p->student?->schoolClass?->label,
                'total_paid_amount' => (float) $p->total_paid_amount,
                'cashier' => $p->cashier?->full_name,
                'deleted_at' => $p->deleted_at?->toIso8601String(),
                'deleted_by' => $deleters[$p->deleted_by_user_id] ?? null,
                'reason' => $p->deletion_reason,
            ])->values(),
            'closings' => $closings->map(fn (CashClosing $c) => [
                'id' => $c->id,
                'closing_date' => $c->closing_date->toDateString(),
                'cashier_id' => $c->cashier_user_id,
                'cashier' => $c->cashier?->full_name,
                'payment_count' => $c->payment_count,
                'expected_amount' => (float) $c->expected_amount,
                'counted_amount' => (float) $c->counted_amount,
                'difference' => (float) $c->difference,
                'note' => $c->note,
                'closed_at' => $c->closed_at?->toIso8601String(),
                'reopened_at' => $c->reopened_at?->toIso8601String(),
                'reopened_by' => $c->reopenedBy?->full_name,
                'reopen_reason' => $c->reopen_reason,
            ])->values(),
        ];
    }

    /** Montant qu'un caissier doit avoir en caisse pour une journée. */
    public function expectedFor(int $cashierId, string $date): array
    {
        $payments = Payment::where('cashier_user_id', $cashierId)->whereDate('payment_date', $date)->get(['total_paid_amount']);

        return ['payment_count' => $payments->count(), 'expected_amount' => $this->sum($payments)];
    }

    private function byClass(Collection $payments): Collection
    {
        return $payments
            ->groupBy(fn ($p) => $p->student?->schoolClass?->label ?? 'Sans classe')
            // Ordre pédagogique réglé dans l'écran Classes ; « Sans classe » en dernier.
            ->sortBy(fn (Collection $rows) => $rows->first()->student?->schoolClass?->sort_order ?? PHP_INT_MAX)
            ->map(fn (Collection $rows, $classLabel) => [
                'class' => $classLabel,
                'payment_count' => $rows->count(),
                'total_amount' => $this->sum($rows),
                'students' => $rows->groupBy('student_id')
                    ->map(fn (Collection $studentPayments) => [
                        'student_id' => $studentPayments->first()->student_id,
                        'matricule' => $studentPayments->first()->student?->matricule,
                        'full_name' => trim(($studentPayments->first()->student?->last_name ?? '').' '.($studentPayments->first()->student?->first_name ?? '')),
                        'total_amount' => $this->sum($studentPayments),
                        'payments' => $studentPayments->map(fn (Payment $p) => [
                            'id' => $p->id,
                            'reference_code' => $p->reference_code,
                            'payment_date' => $p->payment_date->toDateString(),
                            'created_at' => $p->created_at?->toIso8601String(),
                            'cashier' => $p->cashier?->full_name,
                            'total_paid_amount' => (float) $p->total_paid_amount,
                            'lines' => $p->items->map(fn ($item) => ['label' => $item->label(), 'amount' => (float) $item->paid_amount])->values(),
                        ])->values(),
                    ])
                    ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values(),
            ])
            ->values();
    }

    private function sum(Collection $payments): float
    {
        return round((float) $payments->sum('total_paid_amount'), 2);
    }
}
