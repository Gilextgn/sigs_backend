<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Models\CashHandover;
use Modules\Payments\Models\Payment;
use Modules\Payments\Services\CashReport;
use Modules\Security\Models\AuditLog;

class CashController extends Controller
{
    public function __construct(private CashReport $report)
    {
    }

    /**
     * Point sur une période. Sans "cash.report", un caissier ne voit que sa
     * propre caisse, quel que soit le caissier demandé.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'cashier_id' => ['nullable', 'integer'],
        ]);

        $from = Carbon::parse($data['from'] ?? today())->toDateString();
        $to = Carbon::parse($data['to'] ?? $from)->toDateString();
        abort_if(Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366, 422, 'La période ne peut pas dépasser un an.');

        $user = $request->user();
        $cashierId = $user->hasPermission('cash.report') ? ($data['cashier_id'] ?? null) : $user->id;

        return response()->json([
            ...$this->report->build($from, $to, $cashierId),
            // Argent encaissé par l'utilisateur et pas encore remis au directeur.
            'my_pending' => $this->report->pendingFor($user->id),
        ]);
    }

    /**
     * Remises en attente : pour chaque caissier, ce qu'il a encaissé depuis sa
     * dernière remise. Le directeur voit tout le monde, un caissier se voit lui.
     */
    public function pending(Request $request)
    {
        $user = $request->user();
        $cashierIds = $user->hasPermission('cash.receive')
            ? Payment::query()->distinct()->pluck('cashier_user_id')
            : collect([$user->id]);
        $names = \App\Models\User::withoutGlobalScopes()->whereIn('id', $cashierIds)->pluck('full_name', 'id');

        return response()->json(
            $cashierIds->map(fn ($id) => ['cashier_id' => (int) $id, 'cashier' => $names[$id] ?? '—', ...$this->report->pendingFor((int) $id)])
                ->filter(fn ($row) => $row['payment_count'] > 0)
                ->sortByDesc('expected_amount')
                ->values()
        );
    }

    /**
     * Le directeur reçoit l'argent d'un caissier : il saisit ce qu'il a compté.
     * La remise couvre tous les paiements du caissier jusqu'à cet instant ;
     * ils ne peuvent plus être annulés.
     */
    public function receive(Request $request)
    {
        $data = $request->validate([
            'cashier_user_id' => ['required', 'integer'],
            'received_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $handover = DB::transaction(function () use ($data, $request) {
            // Verrou : deux remises simultanées ne couvrent pas deux fois les mêmes paiements.
            DB::table('users')->where('id', $data['cashier_user_id'])->lockForUpdate()->first();
            $pending = $this->report->pendingFor((int) $data['cashier_user_id']);
            abort_if($pending['payment_count'] === 0, 422, "Ce caissier n'a rien à remettre.");

            $difference = round((float) $data['received_amount'] - $pending['expected_amount'], 2);
            if ($difference != 0 && blank($data['note'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'note' => "Le montant reçu ne correspond pas au montant attendu : expliquez l'écart.",
                ]);
            }

            return CashHandover::create([
                'cashier_user_id' => $data['cashier_user_id'],
                'received_by_user_id' => $request->user()->id,
                'to_payment_id' => $pending['last_payment_id'],
                'payment_count' => $pending['payment_count'],
                'expected_amount' => $pending['expected_amount'],
                'received_amount' => $data['received_amount'],
                'difference' => $difference,
                'note' => $data['note'] ?? null,
                'created_at' => now(),
            ]);
        });

        AuditLog::record('cash.handover', 'CashHandover', (string) $handover->id, [
            'cashier_user_id' => $handover->cashier_user_id,
            'expected_amount' => (float) $handover->expected_amount,
            'received_amount' => (float) $handover->received_amount,
            'difference' => (float) $handover->difference,
        ]);

        return response()->json($this->show($handover)->getData(true), 201);
    }

    /** Détail d'une remise (bordereau) : les paiements couverts. */
    public function show(CashHandover $handover)
    {
        $abortUnlessAllowed = request()->user()->hasPermission('cash.receive') || request()->user()->id === $handover->cashier_user_id;
        abort_unless($abortUnlessAllowed, 403, 'Ce bordereau ne vous concerne pas.');

        $previous = (int) CashHandover::where('cashier_user_id', $handover->cashier_user_id)->where('id', '<', $handover->id)->max('to_payment_id');
        $payments = Payment::with(['student:id,first_name,last_name,matricule,class_id', 'student.schoolClass:id,label'])
            ->where('cashier_user_id', $handover->cashier_user_id)
            ->where('id', '>', $previous)->where('id', '<=', $handover->to_payment_id)
            ->orderBy('id')->get()
            ->map(fn (Payment $p) => [
                'reference_code' => $p->reference_code,
                'payment_date' => $p->payment_date->toDateString(),
                'student' => $p->student ? trim($p->student->last_name.' '.$p->student->first_name) : null,
                'class' => $p->student?->schoolClass?->label,
                'amount' => (float) $p->total_paid_amount,
            ]);

        return response()->json([...CashReport::handoverRow($handover->load('cashier:id,full_name', 'receiver:id,full_name')), 'payments' => $payments]);
    }
}
