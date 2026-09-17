<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Http\Requests\StorePaymentRequest;
use Modules\Payments\Models\Payment;
use Modules\Payments\Services\PaymentLineBalances;
use Modules\Payments\Services\PaymentService;
use Modules\Security\Models\AuditLog;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService)
    {
    }

    public function index(Request $request)
    {
        $payments = Payment::with([
            'student:id,matricule,first_name,last_name,class_id',
            'student.schoolClass:id,label',
            'cashier:id,full_name',
            'items.tuitionInstallment:id,label',
            'items.feeType:id,label',
        ])
            ->when($request->student_id, fn ($q, $id) => $q->where('student_id', $id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        PaymentLineBalances::attach($payments->getCollection());

        return $payments;
    }

    public function dailySummary(Request $request)
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $payments = Payment::whereDate('payment_date', $date)->get();

        return response()->json([
            'date' => $date,
            'payment_count' => $payments->count(),
            'total_paid_amount' => round((float) $payments->sum('total_paid_amount'), 2),
            'by_cashier' => DB::table('payments')
                ->join('users', 'users.id', '=', 'payments.cashier_user_id')
                ->whereDate('payments.payment_date', $date)
                ->whereNull('payments.deleted_at')
                ->select('users.id', 'users.full_name', DB::raw('COUNT(payments.id) as payment_count'), DB::raw('SUM(payments.total_paid_amount) as total_paid_amount'))
                ->groupBy('users.id', 'users.full_name')
                ->get(),
        ]);
    }

    // "un paiement n'est pas modifiable après sa création" : seules ces 3 actions existent.
    public function store(StorePaymentRequest $request)
    {
        $payment = $this->paymentService->create(
            studentId: $request->integer('student_id'),
            items: $request->input('items'),
            cashierUserId: $request->user()->id,
        );

        return response()->json($payment, 201);
    }

    public function show(Payment $payment)
    {
        $payment->load('student.schoolClass', 'cashier:id,full_name', 'items.tuitionInstallment', 'items.feeType');
        PaymentLineBalances::attach([$payment]);

        return $payment;
    }

    public function destroy(Payment $payment)
    {
        abort_unless(request()->user()->hasPermission('payments.delete'), 403);
        $payment->delete(); // soft delete : libère les montants pour re-paiement
        AuditLog::record('payment.deleted', 'Payment', (string) $payment->id, [
            'reference_code' => $payment->reference_code,
        ]);

        return response()->noContent();
    }
}
