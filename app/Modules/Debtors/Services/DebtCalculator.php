<?php

namespace Modules\Debtors\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Fees\Models\FeeType;
use Modules\SchoolClasses\Models\SchoolClass;

/**
 * Reste-dû d'un élève pour une classe donnée, optionnellement scopé à une
 * année scolaire précise (via payments.academic_year_id) et/ou une tranche.
 *
 * Avec $academicYearId = null, reproduit le comportement historique de
 * /api/debtors ("tous les paiements jamais faits, classe actuelle"). Avec
 * un $academicYearId précis, sert au calcul de clôture d'année et au
 * blocage de réinscription — même logique, juste filtrée par année.
 */
class DebtCalculator
{
    /** Marge sous la limite de paramètres liés de SQLite (999). */
    private const CHUNK = 500;

    /**
     * $preloadedClass évite une requête quand l'appelant a déjà chargé la
     * classe (avec sa relation "installments").
     */
    public function calculate(int $studentId, int $classId, ?int $academicYearId = null, ?int $trancheId = null, ?SchoolClass $preloadedClass = null): array
    {
        return $this->calculateMany(
            [['student_id' => $studentId, 'class_id' => $classId, 'class' => $preloadedClass]],
            $academicYearId,
            $trancheId,
        )[$studentId];
    }

    /**
     * Même calcul que calculate() pour toute une liste d'élèves, en un
     * nombre de requêtes fixe quel que soit l'effectif : les écrans qui
     * listent l'école entière (débiteurs, accueil, clôture) ne font plus
     * trois requêtes par élève.
     *
     * @param  iterable<array{student_id:int, class_id:int, class?:?SchoolClass}>  $rows
     * @return array<int, array> indexé par student_id
     */
    public function calculateMany(iterable $rows, ?int $academicYearId = null, ?int $trancheId = null): array
    {
        $rows = collect($rows);

        if ($rows->isEmpty()) {
            return [];
        }

        $classes = $this->classesFor($rows);
        $paid = $this->paidLines($rows->pluck('student_id')->unique()->values(), $academicYearId);
        $feesByClass = $this->mandatoryFeesByClass();

        $results = [];

        foreach ($rows as $row) {
            $studentId = (int) $row['student_id'];
            $classId = (int) $row['class_id'];
            $schoolClass = $classes[$classId] ?? null;
            $studentPaid = $paid->get($studentId, collect());

            $trancheLines = $studentPaid->where('item_type', 'TRANCHE');
            if ($trancheId) {
                $trancheLines = $trancheLines->where('tuition_installment_id', $trancheId);
            }

            $theoretical = $this->theoreticalAmount($schoolClass, $trancheId);
            $paidAmount = (float) $trancheLines->sum('paid');

            $results[$studentId] = [
                'theoretical_amount' => round($theoretical, 2),
                'paid_amount' => round($paidAmount, 2),
                'outstanding_amount' => round(max($theoretical - $paidAmount, 0), 2),
                'unpaid_items' => $this->unpaidItems($studentPaid, $schoolClass, $feesByClass->get($classId, collect())),
            ];
        }

        return $results;
    }

    /** @return array<int, SchoolClass> */
    private function classesFor(Collection $rows): array
    {
        $classes = [];
        foreach ($rows as $row) {
            if (! empty($row['class'])) {
                $classes[(int) $row['class_id']] = $row['class'];
            }
        }

        $missing = $rows->pluck('class_id')->map(fn ($id) => (int) $id)->unique()
            ->reject(fn ($id) => isset($classes[$id]))
            ->values();

        if ($missing->isNotEmpty()) {
            SchoolClass::with('installments')->whereIn('id', $missing)->get()
                ->each(function (SchoolClass $class) use (&$classes) {
                    $classes[$class->id] = $class;
                });
        }

        return $classes;
    }

    /**
     * Sommes versées par élève, groupées par ligne (tranche ou frais).
     *
     * @return Collection<int, Collection>
     */
    private function paidLines(Collection $studentIds, ?int $academicYearId): Collection
    {
        return $studentIds->chunk(self::CHUNK)->flatMap(function (Collection $chunk) use ($academicYearId) {
            return DB::table('payment_items')
                ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
                ->whereIn('payments.student_id', $chunk->all())
                ->whereNull('payments.deleted_at')
                ->when($academicYearId !== null, fn ($q) => $q->where('payments.academic_year_id', $academicYearId))
                ->select(
                    'payments.student_id',
                    'payment_items.item_type',
                    'payment_items.tuition_installment_id',
                    'payment_items.fee_type_id',
                    DB::raw('SUM(payment_items.paid_amount) as paid'),
                )
                ->groupBy('payments.student_id', 'payment_items.item_type', 'payment_items.tuition_installment_id', 'payment_items.fee_type_id')
                ->get();
        })->groupBy('student_id');
    }

    /** @return Collection<int, Collection<int, FeeType>> frais obligatoires actifs, par classe */
    private function mandatoryFeesByClass(): Collection
    {
        $byClass = collect();

        FeeType::with('classes:classes.id')
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->get()
            ->each(function (FeeType $fee) use ($byClass) {
                foreach ($fee->classes as $class) {
                    $byClass->put($class->id, $byClass->get($class->id, collect())->push($fee));
                }
            });

        return $byClass;
    }

    private function theoreticalAmount(?SchoolClass $schoolClass, ?int $trancheId): float
    {
        if ($trancheId) {
            return (float) ($schoolClass?->installments->firstWhere('id', $trancheId)?->amount ?? 0);
        }

        return (float) ($schoolClass?->tuition_amount ?? 0);
    }

    private function unpaidItems(Collection $studentPaid, ?SchoolClass $schoolClass, Collection $fees): array
    {
        $paidByTranche = $studentPaid->where('item_type', 'TRANCHE')->pluck('paid', 'tuition_installment_id');
        $paidByFee = $studentPaid->where('item_type', 'AUTRE_FRAIS')->pluck('paid', 'fee_type_id');

        $installments = ($schoolClass?->installments ?? collect())
            ->map(fn ($installment) => $this->line('TRANCHE', $installment->id, $installment->label, (float) $installment->amount, (float) ($paidByTranche[$installment->id] ?? 0)));

        $feeLines = $fees
            ->map(fn (FeeType $fee) => $this->line('AUTRE_FRAIS', $fee->id, $fee->label, (float) $fee->amount, (float) ($paidByFee[$fee->id] ?? 0)));

        return $installments->concat($feeLines)
            ->filter(fn ($item) => $item['remaining'] > 0)
            ->values()
            ->all();
    }

    /**
     * Une ligne partiellement réglée est un acompte : elle reste due, mais
     * l'écran et le reçu doivent afficher ce qui a déjà été versé.
     */
    private function line(string $type, int $id, string $label, float $amount, float $paid): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'amount' => round($amount, 2),
            'paid' => round($paid, 2),
            'remaining' => round(max($amount - $paid, 0), 2),
            'status' => $paid <= 0 ? 'unpaid' : 'partial',
        ];
    }
}
