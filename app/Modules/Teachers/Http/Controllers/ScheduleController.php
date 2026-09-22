<?php

namespace Modules\Teachers\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Teachers\Models\ClassSchedule;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        return ClassSchedule::with(['schoolClass:id,label', 'subject:id,label', 'assignment.teacher:id,full_name'])
            ->when($request->class_id, fn ($query, $id) => $query->where('class_id', $id))
            // Emploi du temps d'un enseignant : ses cours dans toutes ses classes.
            ->when($request->teacher_id, fn ($query, $id) => $query->whereHas('assignment', fn ($q) => $q->where('teacher_id', $id)))
            ->where('is_active', true)
            ->orderBy('day_of_week')->orderBy('starts_at')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', \App\Support\SchoolRule::exists('academic_years')],
            'class_id' => ['required', \App\Support\SchoolRule::exists('classes')],
            'subject_id' => ['required', \App\Support\SchoolRule::exists('subjects')],
            'teacher_assignment_id' => ['required', \App\Support\SchoolRule::exists('teacher_assignments')],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:80'],
        ]);

        $assignment = \Modules\Teachers\Models\TeacherAssignment::findOrFail($data['teacher_assignment_id']);
        abort_unless($assignment->class_id === (int) $data['class_id'] && $assignment->subject_id === (int) $data['subject_id'], 422, 'Cette affectation ne correspond pas à la classe et à la matière choisies.');

        $this->assertFree((int) $data['class_id'], $assignment->teacher_id, (int) $data['day_of_week'], $data['starts_at'], $data['ends_at']);

        return response()->json(ClassSchedule::create($data), 201);
    }

    public function update(Request $request, ClassSchedule $schedule)
    {
        $data = $request->validate([
            'day_of_week' => ['sometimes', 'integer', 'between:1,7'],
            'starts_at' => ['sometimes', 'date_format:H:i'],
            'ends_at' => ['sometimes', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ]);

        // Déplacer un créneau peut créer le même chevauchement qu'en créer un.
        $merged = [...$schedule->only(['day_of_week', 'starts_at', 'ends_at']), ...$data];

        if ($data['is_active'] ?? $schedule->is_active) {
            $this->assertFree(
                $schedule->class_id,
                $schedule->assignment?->teacher_id,
                (int) $merged['day_of_week'],
                substr((string) $merged['starts_at'], 0, 5),
                substr((string) $merged['ends_at'], 0, 5),
                ignoreId: $schedule->id,
            );
        }

        $schedule->update($data);

        return $schedule->fresh();
    }

    /**
     * Personne ne peut être à deux endroits à la fois : ni la classe (deux
     * cours en même temps), ni l'enseignant (un cours dans une autre classe
     * sur le même horaire, même un autre jour de la semaine mis à part).
     */
    private function assertFree(int $classId, ?int $teacherId, int $day, string $startsAt, string $endsAt, ?int $ignoreId = null): void
    {
        $overlapping = fn () => ClassSchedule::query()
            ->where('day_of_week', $day)
            ->where('is_active', true)
            ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        $classClash = $overlapping()->where('class_id', $classId)->with('subject:id,label')->first();
        abort_if(
            $classClash !== null,
            422,
            'Cette classe a déjà un cours sur ce créneau : '
                .($classClash?->subject?->label ?? 'cours').' de '.substr((string) $classClash?->starts_at, 0, 5).' à '.substr((string) $classClash?->ends_at, 0, 5).'.',
        );

        if (! $teacherId) {
            return;
        }

        $teacherClash = $overlapping()
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacherId))
            ->with(['schoolClass:id,label', 'assignment.teacher:id,full_name'])
            ->first();

        abort_if(
            $teacherClash !== null,
            422,
            'Cet enseignant a déjà cours sur ce créneau en '
                .($teacherClash?->schoolClass?->label ?? 'une autre classe')
                .' de '.substr((string) $teacherClash?->starts_at, 0, 5).' à '.substr((string) $teacherClash?->ends_at, 0, 5)
                .' : il ne peut pas être aux deux endroits.',
        );
    }

    public function destroy(ClassSchedule $schedule)
    {
        $schedule->update(['is_active' => false]);

        return response()->noContent();
    }
}
