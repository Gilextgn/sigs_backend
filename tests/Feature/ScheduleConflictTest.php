<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\Teachers\Models\Subject;
use Modules\Teachers\Models\Teacher;
use Modules\Teachers\Models\TeacherAssignment;
use Tests\TestCase;

/**
 * Un enseignant ne peut pas être dans deux classes à la même heure, et une
 * classe ne peut pas avoir deux cours en même temps.
 */
class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function assignment(Teacher $teacher, SchoolClass $class, Subject $subject): TeacherAssignment
    {
        return TeacherAssignment::create([
            'school_id' => 1,
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'hourly_rate' => 4000,
        ]);
    }

    private function teacher(string $name): Teacher
    {
        return Teacher::create(['school_id' => 1, 'full_name' => $name, 'status' => 'active']);
    }

    private function slot(TeacherAssignment $assignment, array $overrides = []): array
    {
        return [
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'teacher_assignment_id' => $assignment->id,
            'day_of_week' => 3,
            'starts_at' => '10:30',
            'ends_at' => '12:30',
            ...$overrides,
        ];
    }

    public function test_a_teacher_cannot_be_in_two_classes_at_the_same_time(): void
    {
        $user = $this->userWithPermissions();
        $subject = Subject::firstOrFail();
        $teacher = $this->teacher('SANDA Merise');
        $firstClass = $this->createSchoolClass();
        $secondClass = $this->createSchoolClass();

        $this->actingAs($user)->postJson('/api/schedules', $this->slot($this->assignment($teacher, $firstClass, $subject)))->assertCreated();

        // Même enseignant, autre classe, créneau qui chevauche : refusé.
        $second = $this->assignment($teacher, $secondClass, $subject);
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($second, ['starts_at' => '11:30', 'ends_at' => '13:00']))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'ne peut pas être aux deux endroits'));

        // Juste après le premier cours : accepté.
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($second, ['starts_at' => '12:30', 'ends_at' => '14:00']))->assertCreated();

        // Un autre enseignant sur le même créneau et une autre classe : accepté.
        $third = $this->assignment($this->teacher('AGBO Paul'), $this->createSchoolClass(), $subject);
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($third))->assertCreated();
    }

    public function test_a_class_cannot_have_two_courses_at_the_same_time(): void
    {
        $user = $this->userWithPermissions();
        $subject = Subject::firstOrFail();
        $class = $this->createSchoolClass();

        $this->actingAs($user)->postJson('/api/schedules', $this->slot($this->assignment($this->teacher('SANDA Merise'), $class, $subject)))->assertCreated();

        $other = $this->assignment($this->teacher('AGBO Paul'), $class, $subject);
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($other, ['starts_at' => '09:00', 'ends_at' => '11:00']))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'déjà un cours sur ce créneau'));
    }

    public function test_moving_a_slot_onto_a_taken_one_is_refused(): void
    {
        $user = $this->userWithPermissions();
        $subject = Subject::firstOrFail();
        $teacher = $this->teacher('SANDA Merise');
        $assignment = $this->assignment($teacher, $this->createSchoolClass(), $subject);

        $morning = $this->actingAs($user)->postJson('/api/schedules', $this->slot($assignment, ['starts_at' => '08:00', 'ends_at' => '10:00']))->assertCreated()->json('id');
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($assignment, ['starts_at' => '10:30', 'ends_at' => '12:30']))->assertCreated();

        $this->actingAs($user)->putJson("/api/schedules/{$morning}", ['starts_at' => '11:00', 'ends_at' => '12:00'])->assertStatus(422);

        // Déplacement vers un créneau libre : accepté.
        $this->actingAs($user)->putJson("/api/schedules/{$morning}", ['starts_at' => '07:00', 'ends_at' => '08:30'])->assertOk();
    }

    public function test_the_timetable_can_be_read_per_class_or_per_teacher(): void
    {
        $user = $this->userWithPermissions();
        $subject = Subject::firstOrFail();
        $teacher = $this->teacher('SANDA Merise');
        $classA = $this->createSchoolClass();
        $classB = $this->createSchoolClass();

        $this->actingAs($user)->postJson('/api/schedules', $this->slot($this->assignment($teacher, $classA, $subject)))->assertCreated();
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($this->assignment($teacher, $classB, $subject), ['day_of_week' => 4]))->assertCreated();
        $this->actingAs($user)->postJson('/api/schedules', $this->slot($this->assignment($this->teacher('AGBO Paul'), $classA, $subject), ['day_of_week' => 5]))->assertCreated();

        $this->actingAs($user)->getJson("/api/schedules?class_id={$classA->id}")->assertOk()->assertJsonCount(2);
        $this->actingAs($user)->getJson("/api/schedules?teacher_id={$teacher->id}")->assertOk()->assertJsonCount(2);
    }
}
