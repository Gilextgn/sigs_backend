<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Http\Requests\StorePaymentRequest;
use Modules\Payments\Models\CashClosing;
use Modules\Payments\Models\Payment;
use Modules\Payments\Services\PaymentLineBalances;
use Modules\Payments\Services\PaymentService;
use Modules\Payments\Services\ReceiptNotifier;
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
                ->where('payments.school_id', \App\Support\CurrentSchool::id())
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

        // Envoi au parent une fois la réponse partie : la caisse n'attend pas le
        // serveur de mail ou WhatsApp (pas de worker de file d'attente sur Render).
        $userId = $request->user()->id;
        app()->terminating(fn () => app(ReceiptNotifier::class)->send($payment->fresh(), null, $userId));

        return response()->json($payment->makeVisible('verification_token'), 201);
    }

    public function show(Payment $payment)
    {
        $payment->load('student.schoolClass', 'student.guardian', 'cashier:id,full_name', 'items.tuitionInstallment', 'items.feeType', 'deliveries');
        PaymentLineBalances::attach([$payment]);

        return $this->withReceiptContacts($payment);
    }

    /** Renvoi manuel du reçu (le parent l'a perdu, ou le contact a été corrigé). */
    public function sendReceipt(Request $request, Payment $payment, ReceiptNotifier $notifier)
    {
        $data = $request->validate(['channels' => ['nullable', 'array'], 'channels.*' => ['in:email,whatsapp']]);

        $deliveries = $notifier->send($payment, $data['channels'] ?? null, $request->user()->id);
        abort_if($deliveries === [], 422, "Aucun e-mail (ni WhatsApp automatique) n'est renseigné pour le parent de cet élève.");

        AuditLog::record('payment.receipt_sent', 'Payment', (string) $payment->id, [
            'reference_code' => $payment->reference_code,
            'channels' => array_map(fn ($d) => "{$d->channel}:{$d->status}", $deliveries),
        ]);

        return $this->show($payment);
    }

    /**
     * Page publique ouverte en scannant le QR code du reçu. Ne révèle que le
     * strict nécessaire pour qu'un parent confronte son reçu papier au serveur.
     */
    public function verify(string $token)
    {
        $payment = Payment::withoutGlobalScope('school')->withTrashed()
            ->with(['student' => fn ($q) => $q->withoutGlobalScope('school'), 'student.schoolClass' => fn ($q) => $q->withoutGlobalScope('school')])
            ->where('verification_token', $token)
            ->first();

        abort_unless($payment && strlen($token) === 40, 404, 'Reçu introuvable.');

        return response()->json([
            'school_name' => ReceiptNotifier::schoolName($payment->school_id),
            'reference_code' => $payment->reference_code,
            'paid_at' => $payment->created_at?->toIso8601String(),
            'total_paid_amount' => (float) $payment->total_paid_amount,
            'currency' => config('school.currency'),
            // Nom complet volontairement tronqué : le lien peut circuler.
            'student' => $payment->student ? $payment->student->first_name.' '.mb_substr((string) $payment->student->last_name, 0, 1).'.' : null,
            'class' => $payment->student?->schoolClass?->label,
            'status' => $payment->trashed() ? 'cancelled' : 'valid',
        ]);
    }

    private function withReceiptContacts(Payment $payment): array
    {
        $guardian = $payment->student?->guardian;

        return [
            ...$payment->makeVisible('verification_token')->toArray(),
            'receipt_contacts' => [
                'email' => $guardian?->email,
                'whatsapp' => $guardian?->whatsapp ? ReceiptNotifier::normalizePhone($guardian->whatsapp) : null,
                'whatsapp_auto' => app(ReceiptNotifier::class)->whatsappConfigured(),
            ],
        ];
    }

    public function destroy(Request $request, Payment $payment)
    {
        abort_unless($request->user()->hasPermission('payments.delete'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        abort_if(
            CashClosing::isClosed($payment->cashier_user_id, $payment->payment_date->toDateString()),
            422,
            'La caisse de cette journée est clôturée : rouvrez-la avant de supprimer ce paiement.',
        );

        // Qui et pourquoi : l'annulation apparaît dans le point de caisse.
        $payment->forceFill(['deleted_by_user_id' => $request->user()->id, 'deletion_reason' => $data['reason']])->save();
        $payment->delete(); // soft delete : libère les montants pour re-paiement
        AuditLog::record('payment.deleted', 'Payment', (string) $payment->id, [
            'reference_code' => $payment->reference_code,
            'total_paid_amount' => (float) $payment->total_paid_amount,
            'reason' => $data['reason'],
        ]);

        return response()->noContent();
    }
}
