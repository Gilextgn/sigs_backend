<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Teachers\Models\Subject;
use Modules\Teachers\Models\Teacher;
use Modules\Teachers\Models\TeachingSession;
use Tests\TestCase;

class TeacherPayTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithPermissions();
    }

    private function teacher(array $data = []): int
    {
        return $this->actingAs($this->admin)->postJson('/api/teachers', [
            'full_name' => 'SANDA Merise', 'phone' => '01 66 18 98 77', 'pay_mode' => 'hourly', 'hourly_rate' => 3000, ...$data,
        ])->assertSuccessful()->json('id');
    }

    /** Affectation sans tarif (reprend celui de la fiche) + séance de 2 h le 7 octobre 2026. */
    private function sessionFor(int $teacherId, ?int $classId = null): TeachingSession
    {
        $classId ??= $this->createSchoolClass()->id;
        $subjectId = Subject::firstOrFail()->id;
        $assignment = $this->actingAs($this->admin)->postJson('/api/teacher-assignments', [
            'teacher_id' => $teacherId, 'class_id' => $classId, 'subject_id' => $subjectId,
        ])->assertSuccessful();
        $this->assertEquals(3000, (float) $assignment->json('hourly_rate'));

        return TeachingSession::create([
            'school_id' => 1, 'class_id' => $classId, 'subject_id' => $subjectId, 'teacher_assignment_id' => $assignment->json('id'),
            'session_date' => '2026-10-07', 'starts_at' => '10:00', 'ends_at' => '12:00', 'planned_minutes' => 120,
        ]);
    }

    private function attend(TeachingSession $session, int $teacherId, array $data)
    {
        return $this->actingAs($this->admin)->postJson('/api/teacher-attendances', ['teaching_session_id' => $session->id, 'teacher_id' => $teacherId, ...$data]);
    }

    public function test_hourly_teacher_needs_a_rate_and_a_10_digit_phone(): void
    {
        $this->actingAs($this->admin)->postJson('/api/teachers', ['full_name' => 'A', 'pay_mode' => 'hourly'])->assertStatus(422)->assertJsonValidationErrors('hourly_rate');
        $this->actingAs($this->admin)->postJson('/api/teachers', ['full_name' => 'A', 'pay_mode' => 'hourly', 'hourly_rate' => 2000, 'phone' => '97000000'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->actingAs($this->admin)->postJson('/api/teachers', ['full_name' => 'A', 'pay_mode' => 'monthly', 'monthly_salary' => 90000])->assertSuccessful()->assertJsonPath('hourly_rate', null);
        $this->assertSame('0166189877', Teacher::find($this->teacher())->phone);
    }

    public function test_lateness_is_deducted_and_the_month_is_summarised(): void
    {
        $teacherId = $this->teacher();
        $session = $this->sessionFor($teacherId);

        $this->attend($session, $teacherId, ['status' => 'late'])->assertStatus(422);
        $this->attend($session, $teacherId, ['status' => 'late', 'absence_minutes' => 30, 'reason' => 'Embouteillage'])->assertSuccessful();

        // 1 h 30 payée à 3 000 XOF/h.
        $this->actingAs($this->admin)->getJson("/api/payroll/estimate?teacher_id={$teacherId}&period=2026-10")
            ->assertOk()->assertJsonPath('worked_minutes', 90)->assertJsonPath('amount', 4500);
        // Novembre repart de zéro.
        $this->actingAs($this->admin)->getJson("/api/payroll/estimate?teacher_id={$teacherId}&period=2026-11")->assertJsonPath('worked_minutes', 0);

        $this->actingAs($this->admin)->postJson('/api/payroll', ['teacher_id' => $teacherId, 'period' => '2026-10', 'bonus_amount' => 500])->assertSuccessful();
        $this->actingAs($this->admin)->getJson("/api/payroll/annual-summary?teacher_id={$teacherId}&start_year=2026")
            ->assertOk()
            ->assertJsonPath('school_year', '2026-2027')
            ->assertJsonCount(1, 'months')
            ->assertJsonPath('months.0.period', '2026-10')
            ->assertJsonPath('totals.net_amount', 5000)
            ->assertJsonPath('totals.pending_amount', 5000);
    }

    public function test_a_replaced_session_is_paid_to_the_substitute(): void
    {
        $holder = $this->teacher();
        $substitute = $this->teacher(['full_name' => 'Remplaçant', 'hourly_rate' => 2000]);
        $session = $this->sessionFor($holder);

        $this->attend($session, $holder, ['status' => 'replaced'])->assertStatus(422);
        $this->attend($session, $holder, ['status' => 'replaced', 'replacement_teacher_id' => $substitute])->assertSuccessful();

        $this->actingAs($this->admin)->getJson("/api/payroll/estimate?teacher_id={$holder}&period=2026-10")->assertJsonPath('worked_minutes', 0);
        $this->actingAs($this->admin)->getJson("/api/payroll/estimate?teacher_id={$substitute}&period=2026-10")
            ->assertJsonPath('worked_minutes', 120)->assertJsonPath('amount', 4000);
    }

    public function test_a_slot_can_be_edited_and_old_clashes_are_flagged(): void
    {
        $teacherId = $this->teacher();
        $classA = $this->createSchoolClass()->id;
        $classB = $this->createSchoolClass()->id;
        $subjectId = Subject::firstOrFail()->id;
        $assign = fn ($classId) => $this->actingAs($this->admin)->postJson('/api/teacher-assignments', ['teacher_id' => $teacherId, 'class_id' => $classId, 'subject_id' => $subjectId])->json('id');
        $slot = fn ($classId, $assignmentId, $start, $end) => $this->actingAs($this->admin)->postJson('/api/schedules', [
            'class_id' => $classId, 'subject_id' => $subjectId, 'teacher_assignment_id' => $assignmentId, 'day_of_week' => 3, 'starts_at' => $start, 'ends_at' => $end,
        ]);

        $first = $slot($classA, $assign($classA), '10:30', '12:30')->assertCreated()->json('id');
        $second = $slot($classB, $assign($classB), '08:00', '10:00')->assertCreated()->json('id');

        // Déplacer le second sur l'horaire du premier : refusé (même enseignant).
        $this->actingAs($this->admin)->putJson("/api/schedules/{$second}", ['starts_at' => '11:00', 'ends_at' => '12:00'])->assertStatus(422);
        $this->actingAs($this->admin)->putJson("/api/schedules/{$second}", ['day_of_week' => 4, 'room' => 'B2'])->assertOk()->assertJsonPath('room', 'B2');

        // Un chevauchement saisi avant le contrôle est signalé dans la liste.
        \Modules\Teachers\Models\ClassSchedule::whereKey($second)->update(['day_of_week' => 3, 'starts_at' => '10:30', 'ends_at' => '12:30']);
        $rows = collect($this->actingAs($this->admin)->getJson('/api/schedules')->assertOk()->json())->keyBy('id');
        $this->assertNotNull($rows[$first]['conflict']);
    }

    public function test_a_paid_fee_cannot_be_deleted_and_says_why(): void
    {
        $classId = $this->createSchoolClass()->id;
        $feeId = $this->actingAs($this->admin)->postJson('/api/fees', ['label' => 'Excursion', 'amount' => 5000, 'class_ids' => [$classId]])->json('id');
        $guardian = \Modules\Students\Models\Guardian::create(['full_name' => 'P', 'relationship_label' => 'Père', 'phone' => '0166000000']);
        $student = \Modules\Students\Models\Student::create([
            'school_id' => 1, 'class_id' => $classId, 'guardian_id' => $guardian->id, 'registration_year' => 2026, 'registration_sequence' => 1,
            'matricule' => 'ELV-X', 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active',
        ]);
        $this->actingAs($this->admin)->postJson('/api/payments', ['student_id' => $student->id, 'items' => [['item_type' => 'AUTRE_FRAIS', 'fee_type_id' => $feeId, 'paid_amount' => 5000]]])->assertCreated();

        $this->actingAs($this->admin)->deleteJson("/api/fees/{$feeId}")->assertStatus(422)->assertJsonFragment(['message' => 'Impossible de supprimer « Excursion » : il a déjà été encaissé 1 fois et figure sur des reçus. Retirez-lui ses classes (ou passez-le « sur inscription » sans inscrit) pour qu\'il ne soit plus demandé.']);
    }
}
