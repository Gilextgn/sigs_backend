<?php

namespace Modules\Debtors\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AcademicYears\Models\AcademicYear;
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
 *
 * Lignes : chaque tranche, chaque frais payé une fois, et pour un frais
 * mensuel (cantine, TD) une ligne par mois de l'année scolaire. Un mois
 * n'est dû qu'à partir de son premier jour ; avant, il reste payable d'avance.
 */
class DebtCalculator
{
    /** Marge sous la limite de paramètres liés de SQLite (999). */
    private const CHUNK = 500;

    /**
     * $preloadedClass évite une requête quand l'appelant a déjà chargé la
     * classe (avec sa relation "installments").
     */
    public function calculate(int $studentId, int $classId, ?int $academicYearId = null, ?int $trancheId = null, ?SchoolClass $preloadedClass = null, bool $withAllLines = false): array
    {
        return $this->calculateMany(
            [['student_id' => $studentId, 'class_id' => $classId, 'class' => $preloadedClass]],
            $academicYearId,
            $trancheId,
            $withAllLines,
        )[$studentId];
    }

    /**
     * Même calcul que calculate() pour toute une liste d'élèves, en un
     * nombre de requêtes fixe quel que soit l'effectif : les écrans qui
     * listent l'école entière (débiteurs, accueil, clôture) ne font plus
     * trois requêtes par élève.
     *
     * $withAllLines ajoute "lines" : toutes les lignes encaissables, y compris
     * soldées, facultatives ou à venir (écran d'encaissement).
     *
     * @param  iterable<array{student_id:int, class_id:int, class?:?SchoolClass}>  $rows
     * @return array<int, array> indexé par student_id
     */
    public function calculateMany(iterable $rows, ?int $academicYearId = null, ?int $trancheId = null, bool $withAllLines = false): array
    {
        $rows = collect($rows);

        if ($rows->isEmpty()) {
            return [];
        }

        $studentIds = $rows->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values();
        $classes = $this->classesFor($rows);
        $paid = $this->paidLines($studentIds, $academicYearId);
        $feesByClass = $this->activeFeesByClass();
        $subscriptions = $this->subscriptionsFor($studentIds);
        // Les frais mensuels se comptent sur une année précise : celle demandée, sinon l'année en cours.
        $year = $academicYearId ? AcademicYear::find($academicYearId) : AcademicYear::where('is_active', true)->first();

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

            $outstanding = round(max($theoretical - $paidAmount, 0), 2);
            $lines = $this->lines(
                $studentPaid,
                $schoolClass,
                $feesByClass->get($schoolClass?->pricing_class_id ?? $classId, collect()),
                $subscriptions->get($studentId, collect()),
                $year,
            );
            // Dû = ligne obligatoire (ou frais auquel l'élève est inscrit), échue, non soldée.
            $unpaidItems = $lines->filter(fn ($line) => $line['owed'] && $line['due'] && $line['remaining'] > 0)->values()->all();

            $results[$studentId] = [
                'theoretical_amount' => round($theoretical, 2),
                'paid_amount' => round($paidAmount, 2),
                'outstanding_amount' => $outstanding,
                'unpaid_items' => $unpaidItems,
                // Frais échus non réglés (cantine, TD, tenue…), hors scolarité.
                'fees_outstanding' => round((float) collect($unpaidItems)->where('type', 'AUTRE_FRAIS')->sum('remaining'), 2),
                // Part du reste-dû qu'aucune tranche ne porte : tranches de la
                // classe inférieures à sa scolarité, ou supprimées après coup.
                // Invisible dans la liste des lignes, et donc inencaissable :
                // les écrans doivent le signaler au lieu d'annoncer « tout est réglé ».
                'unlisted_amount' => round(max($outstanding - collect($unpaidItems)->where('type', 'TRANCHE')->sum('remaining'), 0), 2),
                ...($withAllLines ? ['lines' => $lines->values()->all()] : []),
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
     * Sommes versées par élève, groupées par ligne (tranche, frais, et pour un
     * frais mensuel par mois et par année).
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
                    'payments.academic_year_id',
                    'payment_items.item_type',
                    'payment_items.tuition_installment_id',
                    'payment_items.fee_type_id',
                    'payment_items.period_month',
                    DB::raw('SUM(payment_items.paid_amount) as paid'),
                )
                ->groupBy('payments.student_id', 'payments.academic_year_id', 'payment_items.item_type', 'payment_items.tuition_installment_id', 'payment_items.fee_type_id', 'payment_items.period_month')
                ->get();
        })->groupBy('student_id');
    }

    /** @return Collection<int, Collection<int, FeeType>> frais actifs, par classe (obligatoires et facultatifs) */
    private function activeFeesByClass(): Collection
    {
        $byClass = collect();

        FeeType::with('classes:classes.id')
            ->where('is_active', true)
            ->get()
            ->each(function (FeeType $fee) use ($byClass) {
                foreach ($fee->classes as $class) {
                    $byClass->put($class->id, $byClass->get($class->id, collect())->push($fee));
                }
            });

        return $byClass;
    }

    /** @return Collection<int, Collection<int, FeeType>> frais auxquels chaque élève est inscrit */
    private function subscriptionsFor(Collection $studentIds): Collection
    {
        $rows = $studentIds->chunk(self::CHUNK)->flatMap(fn (Collection $chunk) => DB::table('fee_subscriptions')
            ->whereIn('student_id', $chunk->all())
            ->get(['student_id', 'fee_type_id']));

        if ($rows->isEmpty()) {
            return collect();
        }

        $fees = FeeType::where('is_active', true)->whereIn('id', $rows->pluck('fee_type_id')->unique())->get()->keyBy('id');

        return $rows->groupBy('student_id')->map(fn (Collection $studentRows) => $studentRows
            ->map(fn ($row) => $fees->get($row->fee_type_id))
            ->filter()
            ->values());
    }

    private function theoreticalAmount(?SchoolClass $schoolClass, ?int $trancheId): float
    {
        if ($trancheId) {
            return (float) ($schoolClass?->installments->firstWhere('id', $trancheId)?->amount ?? 0);
        }

        return (float) ($schoolClass?->tuition_amount ?? 0);
    }

    /**
     * Toutes les lignes encaissables de l'élève. "owed" : compte comme dette
     * (tranche, frais obligatoire de la classe, frais auquel il est inscrit) ;
     * "due" : échue (un mois à venir ne l'est pas encore).
     */
    private function lines(Collection $studentPaid, ?SchoolClass $schoolClass, Collection $classFees, Collection $subscribedFees, ?AcademicYear $year): Collection
    {
        $sum = fn (Collection $rows) => (float) $rows->sum('paid');

        $installments = ($schoolClass?->installments ?? collect())
            ->map(fn ($installment) => $this->line(
                'TRANCHE', $installment->id, null, $installment->label, (float) $installment->amount,
                $sum($studentPaid->where('item_type', 'TRANCHE')->where('tuition_installment_id', $installment->id)),
                owed: true, due: true,
            ));

        $subscribedIds = $subscribedFees->pluck('id')->all();
        $fees = $classFees->concat($subscribedFees)->unique('id');
        $feePaid = $studentPaid->where('item_type', 'AUTRE_FRAIS');

        $feeLines = $fees->flatMap(function (FeeType $fee) use ($subscribedIds, $feePaid, $sum, $year) {
            $owed = $fee->is_mandatory || in_array($fee->id, $subscribedIds, true);
            $paidRows = $feePaid->where('fee_type_id', $fee->id);

            if (! $fee->isMonthly()) {
                return [$this->line('AUTRE_FRAIS', $fee->id, null, $fee->label, (float) $fee->amount, $sum($paidRows->whereNull('period_month')), $owed, true)];
            }

            // Frais mensuel facultatif sans inscription : rien à encaisser (pas neuf lignes inutiles).
            if (! $owed) {
                return [];
            }

            return collect($fee->billedMonths())->map(fn (int $month) => $this->line(
                'AUTRE_FRAIS', $fee->id, $month, $fee->label.' — '.FeeType::monthLabel($month), (float) $fee->amount,
                $sum($paidRows->where('period_month', $month)->where('academic_year_id', $year?->id)),
                owed: true, due: FeeType::monthIsDue($month, $year),
            ))->all();
        });

        return $installments->concat($feeLines)->values();
    }

    /**
     * Une ligne partiellement réglée est un acompte : elle reste due, mais
     * l'écran et le reçu doivent afficher ce qui a déjà été versé.
     */
    private function line(string $type, int $id, ?int $month, string $label, float $amount, float $paid, bool $owed, bool $due): array
    {
        $remaining = round(max($amount - $paid, 0), 2);

        return [
            'key' => $type === 'TRANCHE' ? "T-{$id}" : ($month ? "M-{$id}-{$month}" : "F-{$id}"),
            'type' => $type,
            'id' => $id,
            'period_month' => $month,
            'label' => $label,
            'group' => $type === 'TRANCHE' ? 'Tranches' : ($month ? 'Frais mensuels' : 'Autres frais'),
            'amount' => round($amount, 2),
            'paid' => round($paid, 2),
            'remaining' => $remaining,
            'status' => $remaining <= 0 ? 'settled' : ($paid <= 0 ? 'unpaid' : 'partial'),
            'owed' => $owed,
            'due' => $due,
        ];
    }
}
