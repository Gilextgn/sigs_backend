<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Debtors\Services\DebtCalculator;
use Modules\Fees\Models\FeeSubscription;
use Modules\Fees\Models\FeeType;
use Modules\Security\Models\AuditLog;
use Modules\Students\Models\Student;

class FeeSubscriptionController extends Controller
{
    /**
     * Frais facultatifs proposés à l'élève (ceux de sa classe, ou auxquels il
     * est déjà inscrit), avec son inscription : cantine, TD…
     */
    public function index(Student $student)
    {
        $pricingClassId = $student->schoolClass?->pricing_class_id;
        $subscribed = FeeSubscription::where('student_id', $student->id)->pluck('fee_type_id')->all();

        return FeeType::where('is_active', true)
            ->where('is_mandatory', false)
            ->where(fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('classes.id', $pricingClassId))->orWhereIn('id', $subscribed))
            ->orderBy('label')
            ->get()
            ->map(fn (FeeType $fee) => [
                'fee_type_id' => $fee->id,
                'label' => $fee->label,
                'amount' => (float) $fee->amount,
                'billing_cycle' => $fee->billing_cycle,
                'months' => $fee->billedMonths(),
                'subscribed' => in_array($fee->id, $subscribed, true),
            ]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'fee_type_ids' => ['present', 'array'],
            'fee_type_ids.*' => ['integer', \App\Support\SchoolRule::exists('fee_types')],
        ]);

        $wanted = FeeType::whereIn('id', $data['fee_type_ids'])->where('is_mandatory', false)->pluck('id')->all();
        $current = FeeSubscription::where('student_id', $student->id)->pluck('fee_type_id')->all();

        DB::transaction(function () use ($student, $wanted, $current) {
            FeeSubscription::where('student_id', $student->id)->whereNotIn('fee_type_id', $wanted)->delete();
            foreach (array_diff($wanted, $current) as $feeTypeId) {
                FeeSubscription::create(['student_id' => $student->id, 'fee_type_id' => $feeTypeId, 'created_at' => now()]);
            }
        });

        AuditLog::record('student.fee_subscriptions', 'Student', (string) $student->id, [
            'added' => array_values(array_diff($wanted, $current)),
            'removed' => array_values(array_diff($current, $wanted)),
        ]);

        return $this->index($student->fresh());
    }

    /** Tout ce qui est encaissable pour l'élève, soldé, facultatif ou à venir compris. */
    public function payableLines(Student $student, DebtCalculator $calculator)
    {
        $student->load('schoolClass.installments');

        return response()->json($calculator->calculate($student->id, $student->class_id, null, null, $student->schoolClass, withAllLines: true));
    }
}
