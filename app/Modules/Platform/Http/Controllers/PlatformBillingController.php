<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Platform\Models\School;
use Modules\Platform\Models\SchoolPayment;
use Modules\Platform\Models\SchoolStatusEvent;

/**
 * L'argent de la plateforme : encaisser l'abonnement d'une école (ce qui
 * prolonge son échéance) et noter les relances envoyées.
 */
class PlatformBillingController extends Controller
{
    public function __construct(private PlatformSchoolController $schools)
    {
    }

    public function storePayment(Request $request, School $school)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            // Nouvelle échéance : calculée si absente, modifiable par le propriétaire.
            'due_after' => ['nullable', 'date'],
            'method' => ['nullable', Rule::in(SchoolPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
            'reactivate' => ['sometimes', 'boolean'],
        ]);

        $dueBefore = $school->subscription_due_at?->copy();
        $dueAfter = isset($data['due_after'])
            ? Carbon::parse($data['due_after'])
            : self::extendDue($dueBefore, (int) $data['months']);

        DB::transaction(function () use ($school, $data, $dueBefore, $dueAfter, $request) {
            SchoolPayment::create([
                'school_id' => $school->id,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'months' => $data['months'],
                'due_before' => $dueBefore,
                'due_after' => $dueAfter,
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'recorded_by_user_id' => $request->user()->id,
            ]);

            $school->subscription_due_at = $dueAfter;

            // Payer, c'est en général la fin d'une suspension manuelle pour
            // impayé ; la suspension automatique, elle, tombe d'elle-même avec
            // la nouvelle échéance.
            $reactivated = ($data['reactivate'] ?? true) && $school->suspended_at !== null;
            if ($reactivated) {
                $school->suspended_at = null;
                $school->suspension_reason = null;
            }

            $school->save();

            $this->record($school, 'payment_recorded', number_format($data['amount'], 0, ',', ' ').' · '.$data['months'].' mois', $request);
            if ($reactivated) {
                $this->record($school, 'reactivated', 'Paiement reçu', $request);
            }
        });

        return $this->schools->show($school->fresh());
    }

    public function destroyPayment(Request $request, School $school, int $paymentId)
    {
        $payment = SchoolPayment::where('school_id', $school->id)->findOrFail($paymentId);

        // L'échéance n'est pas recalculée : elle a pu être retouchée depuis.
        // Le propriétaire l'ajuste lui-même s'il le faut.
        $payment->delete();
        $this->record($school, 'payment_deleted', number_format($payment->amount, 0, ',', ' ').' du '.$payment->paid_at->format('d/m/Y'), $request);

        return $this->schools->show($school->fresh());
    }

    /** Trace d'une relance envoyée (WhatsApp) : évite de relancer deux fois le même jour. */
    public function storeReminder(Request $request, School $school)
    {
        $data = $request->validate(['channel' => ['nullable', 'string', 'max:30']]);

        $this->record($school, 'reminder_sent', $data['channel'] ?? 'whatsapp', $request);

        return response()->json(['last_reminded_at' => now()], 201);
    }

    /**
     * Échéance prolongée de n mois, à partir de l'échéance en cours (une école
     * en retard paie la période qu'elle a utilisée) ; sans échéance, à partir
     * d'aujourd'hui.
     */
    public static function extendDue(?Carbon $currentDue, int $months): Carbon
    {
        return ($currentDue ?? now()->startOfDay())->copy()->addMonthsNoOverflow($months);
    }

    private function record(School $school, string $action, ?string $reason, Request $request): void
    {
        SchoolStatusEvent::create([
            'school_id' => $school->id,
            'action' => $action,
            'reason' => $reason,
            'actor_user_id' => $request->user()->id,
            'created_at' => now(),
        ]);
    }
}
