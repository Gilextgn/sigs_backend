<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Students\Models\Student;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function enrollmentPayload(int $classId, array $overrides = []): array
    {
        return array_replace_recursive([
            'class_id' => $classId,
            'first_name' => 'Hornel',
            'last_name' => 'Amoussouga',
            'birth_date' => '2015-01-05',
            'gender' => 'M',
            'guardian' => [
                'full_name' => 'Bernard Amoussouga',
                'relationship_label' => 'Père',
                'phone' => '66552214',
                'address' => 'Womey',
            ],
        ], $overrides);
    }

    public function test_it_enrolls_a_student_with_an_inline_guardian(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass();

        $response = $this->actingAs($user)
            ->postJson('/api/students', $this->enrollmentPayload($class->id));

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Hornel')
            ->assertJsonPath('data.class.id', $class->id)
            ->assertJsonPath('data.guardian.full_name', 'Bernard Amoussouga')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('guardians', 1);
    }

    public function test_it_generates_sequential_matricules_within_the_same_year(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass();
        $year = (int) date('Y');

        foreach (['Premier', 'Deuxieme', 'Troisieme'] as $firstName) {
            $this->actingAs($user)
                ->postJson('/api/students', $this->enrollmentPayload($class->id, ['first_name' => $firstName]))
                ->assertCreated();
        }

        $matricules = Student::orderBy('id')->pluck('matricule')->all();

        $this->assertSame([
            sprintf('ELV-%d-%06d', $year, 1),
            sprintf('ELV-%d-%06d', $year, 2),
            sprintf('ELV-%d-%06d', $year, 3),
        ], $matricules);
    }

    public function test_it_rejects_an_enrollment_without_a_guardian(): void
    {
        $user = $this->userWithPermissions();
        $class = $this->createSchoolClass();

        $payload = $this->enrollmentPayload($class->id);
        unset($payload['guardian']);

        $this->actingAs($user)
            ->postJson('/api/students', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('guardian');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_it_rejects_an_enrollment_on_an_unknown_class(): void
    {
        $user = $this->userWithPermissions();

        $this->actingAs($user)
            ->postJson('/api/students', $this->enrollmentPayload(9999))
            ->assertStatus(422)
            ->assertJsonValidationErrors('class_id');
    }

    public function test_it_forbids_enrollment_without_the_students_create_permission(): void
    {
        $user = $this->userWithPermissions(['students.view']);
        $class = $this->createSchoolClass();

        $this->actingAs($user)
            ->postJson('/api/students', $this->enrollmentPayload($class->id))
            ->assertForbidden();

        $this->assertDatabaseCount('students', 0);
    }
}
