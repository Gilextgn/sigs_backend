<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Modules\Debtors\Services\ReminderService;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');
        Mail::fake();
        $this->admin = $this->userWithPermissions();
        $class = $this->createSchoolClass(150000);
        // Échue depuis 7 jours, et à échoir dans 7 jours : les dates viennent des tranches.
        TuitionInstallment::create(['class_id' => $class->id, 'label' => '1ère tranche', 'amount' => 50000, 'due_date' => '2026-10-03']);
        TuitionInstallment::create(['class_id' => $class->id, 'label' => '2ème tranche', 'amount' => 50000, 'due_date' => '2026-10-17']);
        TuitionInstallment::create(['class_id' => $class->id, 'label' => '3ème tranche', 'amount' => 50000, 'due_date' => '2027-03-01']);
        $guardian = Guardian::create(['full_name' => 'Mme DOSSOU', 'relationship_label' => 'Mère', 'phone' => '0166000000', 'email' => 'parent@example.test']);
        $this->student = Student::create([
            'school_id' => 1, 'class_id' => $class->id, 'guardian_id' => $guardian->id, 'registration_year' => 2026, 'registration_sequence' => 1,
            'matricule' => 'ELV-1', 'first_name' => 'Awa', 'last_name' => 'DOSSOU', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enable(array $overrides = []): void
    {
        $this->actingAs($this->admin)->putJson('/api/reminders/settings', [
            'enabled' => true, 'days_before' => [7, 1], 'overdue_every' => 7, 'channels' => ['email'],
            'template' => 'Bonjour {parent}, {eleve} ({classe}) doit {montant} : {detail}', ...$overrides,
        ])->assertOk();
    }

    public function test_families_are_grouped_with_the_due_dates_of_their_installments(): void
    {
        $this->enable();

        $response = $this->actingAs($this->admin)->getJson('/api/reminders?horizon=7')->assertOk();
        $response->assertJsonCount(1, 'rows')
            ->assertJsonCount(2, 'rows.0.items') // la 3ème tranche (mars) est hors horizon
            ->assertJsonPath('rows.0.days', -7)
            ->assertJsonPath('rows.0.due_today', true);
        $message = $response->json('rows.0.message');
        $this->assertStringContainsString('Bonjour Mme DOSSOU, Awa DOSSOU', $message);
        $this->assertStringContainsString('100 000 F CFA', $message);
        $this->assertStringContainsString('échue depuis le 03/10/2026', $message);
        $this->assertStringContainsString('échéance le 17/10/2026', $message);
    }

    public function test_sending_a_reminder_is_traced(): void
    {
        $this->enable();

        $this->actingAs($this->admin)->postJson('/api/reminders/send', ['student_ids' => [$this->student->id]])
            ->assertOk()->assertJsonPath('0.logs.0.channel', 'email')->assertJsonPath('0.logs.0.status', 'sent');
        $this->actingAs($this->admin)->postJson("/api/reminders/{$this->student->id}/manual")->assertCreated();

        $this->assertDatabaseCount('reminder_logs', 2);
        $this->actingAs($this->admin)->getJson('/api/reminders')->assertJsonPath('history.0.channel', 'whatsapp_manual');
    }

    public function test_daily_run_happens_once_a_day_and_only_when_enabled(): void
    {
        $service = app(ReminderService::class);
        $this->actingAs($this->admin);
        $this->assertSame(0, $service->runDaily()); // désactivé par défaut

        $this->enable();
        $this->assertSame(1, $service->runDaily());
        $this->assertSame(0, $service->runDaily()); // déjà fait aujourd'hui
        $this->assertDatabaseCount('reminder_logs', 1);
    }

    public function test_only_the_director_changes_the_settings(): void
    {
        $cashier = $this->userWithPermissions(['debtors.print']);
        $this->actingAs($cashier)->getJson('/api/reminders')->assertOk();
        $this->actingAs($cashier)->putJson('/api/reminders/settings', ['enabled' => true, 'days_before' => [], 'overdue_every' => 0, 'channels' => [], 'template' => 'x'])->assertForbidden();
    }
}
