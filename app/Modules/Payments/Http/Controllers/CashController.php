<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Models\CashClosing;
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
            'my_day' => $this->myDay($user->id),
        ]);
    }

    /** Clôture de la caisse du jour de l'utilisateur connecté. */
    public function close(Request $request)
    {
        $data = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = $request->user()->id;
        $today = today()->toDateString();

        $closing = DB::transaction(function () use ($data, $userId, $today) {
            // Verrou : deux clics simultanés ne créent pas deux clôtures.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            abort_if(CashClosing::isClosed($userId, $today), 422, 'Votre caisse du jour est déjà clôturée.');

            $expected = $this->report->expectedFor($userId, $today);
            $difference = round((float) $data['counted_amount'] - $expected['expected_amount'], 2);

            if ($difference != 0 && blank($data['note'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'note' => "Le montant compté ne correspond pas au montant attendu : expliquez l'écart.",
                ]);
            }

            return CashClosing::create([
                'cashier_user_id' => $userId,
                'closing_date' => $today,
                'payment_count' => $expected['payment_count'],
                'expected_amount' => $expected['expected_amount'],
                'counted_amount' => $data['counted_amount'],
                'difference' => $difference,
                'note' => $data['note'] ?? null,
                'closed_at' => now(),
            ]);
        });

        AuditLog::record('cash.closed', 'CashClosing', (string) $closing->id, [
            'closing_date' => $today,
            'expected_amount' => (float) $closing->expected_amount,
            'counted_amount' => (float) $closing->counted_amount,
            'difference' => (float) $closing->difference,
        ]);

        return response()->json($closing, 201);
    }

    /** Réouverture (erreur de saisie, paiement oublié) : motivée et journalisée. */
    public function reopen(Request $request, CashClosing $closing)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        abort_if($closing->reopened_at !== null, 422, 'Cette clôture a déjà été rouverte.');

        $closing->update([
            'reopened_at' => now(),
            'reopened_by_user_id' => $request->user()->id,
            'reopen_reason' => $data['reason'],
        ]);

        AuditLog::record('cash.reopened', 'CashClosing', (string) $closing->id, [
            'closing_date' => $closing->closing_date->toDateString(),
            'cashier_user_id' => $closing->cashier_user_id,
            'reason' => $data['reason'],
        ]);

        return response()->json($closing);
    }

    private function myDay(int $userId): array
    {
        $today = today()->toDateString();

        return [
            'date' => $today,
            ...$this->report->expectedFor($userId, $today),
            'closed' => CashClosing::isClosed($userId, $today),
        ];
    }
}
