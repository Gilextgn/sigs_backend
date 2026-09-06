<?php

namespace Modules\Debtors\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Fees\Models\FeeType;
use Modules\Students\Models\Student;

class DebtorController extends Controller
{
    /**
     * Liste des débiteurs, avec filtre classe / tranche / classe+tranche,
     * équivalent applicatif de la vue SQL v_student_balances.
     */
    public function index(Request $request)
    {
        $query = Student::query()
            ->with(['schoolClass.installments'])
            ->where('status', 'active')
            ->when($request->class_id, fn ($q, $id) => $q->where('class_id', $id));

        $students = $query->get()->map(function (Student $student) use ($request) {
            $theoretical = $this->theoreticalAmount($student, $request->tranche_id);
            $paid = $this->paidAmount($student, $request->tranche_id);

            $unpaidItems = $this->unpaidItems($student);

            return [
                'student_id' => $student->id,
                'matricule' => $student->matricule,
                'full_name' => $student->fullName(),
                'class' => $student->schoolClass?->label,
                'theoretical_amount' => round($theoretical, 2),
                'paid_amount' => round($paid, 2),
                'outstanding_amount' => round(max($theoretical - $paid, 0), 2),
                'unpaid_items' => $unpaidItems,
            ];
        })->filter(fn ($row) => $row['outstanding_amount'] > 0)
            ->sortByDesc('outstanding_amount')
            ->values();

        return response()->json($students);
    }

    private function theoreticalAmount(Student $student, ?int $trancheId): float
    {
        if ($trancheId) {
            return (float) ($student->schoolClass?->installments()->where('id', $trancheId)->value('amount') ?? 0);
        }

        return (float) $student->schoolClass?->tuition_amount;
    }

    private function paidAmount(Student $student, ?int $trancheId): float
    {
        $query = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->where('payments.student_id', $student->id)
            ->whereNull('payments.deleted_at');

        if ($trancheId) {
            $query->where('payment_items.item_type', 'TRANCHE')
                ->where('payment_items.tuition_installment_id', $trancheId);
        } else {
            $query->where('payment_items.item_type', 'TRANCHE');
        }

        return (float) $query->sum('payment_items.paid_amount');
    }

    private function unpaidItems(Student $student): array
    {
        $paidByTranche = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->where('payments.student_id', $student->id)
            ->whereNull('payments.deleted_at')
            ->where('payment_items.item_type', 'TRANCHE')
            ->select('payment_items.tuition_installment_id', DB::raw('SUM(payment_items.paid_amount) as paid'))
            ->groupBy('payment_items.tuition_installment_id')
            ->pluck('paid', 'tuition_installment_id');

        $installments = $student->schoolClass?->installments
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

        $paidByFee = DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->where('payments.student_id', $student->id)
            ->whereNull('payments.deleted_at')
            ->where('payment_items.item_type', 'AUTRE_FRAIS')
            ->select('payment_items.fee_type_id', DB::raw('SUM(payment_items.paid_amount) as paid'))
            ->groupBy('payment_items.fee_type_id')
            ->pluck('paid', 'fee_type_id');

        $fees = FeeType::where('is_active', true)
            ->where('is_mandatory', true)
            ->whereHas('classes', fn ($query) => $query->where('classes.id', $student->class_id))
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
