<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Models\School;
use Modules\Users\Models\Role;
use Tests\TestCase;

/**
 * Encaisser l'abonnement d'une école prolonge son échéance ; les coordonnées
 * et les relances servent à se faire payer à temps.
 */
class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-22 10:00:00');

        $this->owner = User::create([
            'school_id' => null,
            'role_id' => Role::where('code', 'platform_owner')->value('id'),
            'full_name' => 'Propriétaire',
            'email' => 'owner@sigs.test',
            'password' => 'motdepasse',
            'status' => 'active',
        ]);
    }

    private function school(array $attributes = []): School
    {
        return School::create(['name' => 'École B', ...$attributes]);
    }

    public function test_a_payment_extends_the_due_date_from_the_current_one(): void
    {
        $school = $this->school(['subscription_due_at' => '2026-09-10']); // en retard de 12 jours

        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 25000,
            'paid_at' => '2026-09-22',
            'months' => 3,
            'method' => 'mobile_money',
        ])->assertOk()
            ->assertJsonPath('subscription_due_at', '2026-12-10')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('total_paid', 25000)
            ->assertJsonPath('payments.0.due_before', '2026-09-10')
            ->assertJsonPath('payments.0.due_after', '2026-12-10')
            ->assertJsonPath('events.0.action', 'payment_recorded');
    }

    public function test_without_a_due_date_the_period_starts_today_and_can_be_overridden(): void
    {
        $school = $this->school();

        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 10000, 'paid_at' => '2026-09-22', 'months' => 1,
        ])->assertOk()->assertJsonPath('subscription_due_at', '2026-10-22');

        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 10000, 'paid_at' => '2026-09-22', 'months' => 1, 'due_after' => '2026-12-31',
        ])->assertOk()->assertJsonPath('subscription_due_at', '2026-12-31');
    }

    public function test_paying_lifts_a_manual_suspension_unless_asked_otherwise(): void
    {
        $suspended = $this->school(['suspended_at' => now(), 'suspension_reason' => 'Impayé']);
        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$suspended->id}/payments", [
            'amount' => 10000, 'paid_at' => '2026-09-22', 'months' => 1,
        ])->assertOk()->assertJsonPath('status', 'active');

        $kept = $this->school(['suspended_at' => now(), 'suspension_reason' => 'Litige']);
        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$kept->id}/payments", [
            'amount' => 10000, 'paid_at' => '2026-09-22', 'months' => 1, 'reactivate' => false,
        ])->assertOk()->assertJsonPath('status', 'suspended');
    }

    public function test_an_automatic_suspension_for_payment_ends_with_the_new_due_date(): void
    {
        $school = $this->school(['subscription_due_at' => '2026-08-01', 'auto_suspend' => true, 'grace_days' => 5]);
        $this->assertTrue($school->isSuspendedForPayment());

        // Même prolongée depuis l'ancienne échéance, 2 mois ne suffisent pas…
        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 20000, 'paid_at' => '2026-09-22', 'months' => 1,
        ])->assertOk()->assertJsonPath('subscription_due_at', '2026-09-01')->assertJsonPath('status', 'suspended');

        // … un mois de plus, oui.
        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 20000, 'paid_at' => '2026-09-22', 'months' => 1,
        ])->assertOk()->assertJsonPath('subscription_due_at', '2026-10-01')->assertJsonPath('status', 'active');
    }

    public function test_the_console_sums_the_revenue_and_shows_the_last_payment_and_reminder(): void
    {
        $school = $this->school(['subscription_due_at' => '2026-09-25', 'contact_phone' => '2290191489743']);

        foreach ([['2026-09-05', 15000], ['2026-08-20', 10000], ['2025-12-01', 99999]] as [$date, $amount]) {
            $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
                'amount' => $amount, 'paid_at' => $date, 'months' => 1, 'due_after' => '2026-09-25',
            ])->assertOk();
        }

        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/reminders", ['channel' => 'whatsapp'])->assertCreated();

        $response = $this->actingAs($this->owner)->getJson('/api/platform/schools')->assertOk()
            ->assertJsonPath('revenue.this_month', 15000)
            ->assertJsonPath('revenue.last_month', 10000)
            ->assertJsonPath('revenue.this_year', 25000);

        $row = collect($response->json('schools'))->firstWhere('id', $school->id);
        $this->assertSame('2026-09-05', $row['last_payment_at']);
        $this->assertSame('2290191489743', $row['contact_phone']);
        $this->assertStringStartsWith('2026-09-22', (string) $row['last_reminded_at']);
    }

    public function test_the_owner_edits_contact_details_and_can_delete_a_mistaken_payment(): void
    {
        $school = $this->school();

        $this->actingAs($this->owner)->putJson("/api/platform/schools/{$school->id}", [
            'contact_name' => 'M. Directeur',
            'contact_phone' => '+229 01 91 48 97 43',
            'city' => 'Cotonou',
            'notes' => 'Paie par MoMo en début de mois.',
            'plan_amount' => 15000,
        ])->assertOk()->assertJsonPath('city', 'Cotonou')->assertJsonPath('plan_amount', 15000)->assertJsonPath('notes', 'Paie par MoMo en début de mois.');

        $this->actingAs($this->owner)->putJson("/api/platform/schools/{$school->id}", ['contact_phone' => 'pas un numéro'])->assertUnprocessable();

        $paymentId = $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 15000, 'paid_at' => '2026-09-22', 'months' => 1,
        ])->json('payments.0.id');

        $this->actingAs($this->owner)->deleteJson("/api/platform/schools/{$school->id}/payments/{$paymentId}")
            ->assertOk()->assertJsonCount(0, 'payments')->assertJsonPath('events.0.action', 'payment_deleted');
    }

    public function test_billing_is_reserved_to_the_owner_and_future_payments_are_refused(): void
    {
        $school = $this->school();

        $this->actingAs($this->userWithPermissions())->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 1000, 'paid_at' => '2026-09-22', 'months' => 1,
        ])->assertForbidden();

        $this->actingAs($this->owner)->postJson("/api/platform/schools/{$school->id}/payments", [
            'amount' => 1000, 'paid_at' => '2026-10-01', 'months' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
    }
}
