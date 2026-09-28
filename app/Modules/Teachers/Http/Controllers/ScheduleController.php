<?php

namespace Modules\Teachers\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Teachers\Models\ClassSchedule;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $rows = ClassSchedule::with(['schoolClass:id,label', 'subject:id,label', 'assignment.teacher:id,full_name'])
            ->when($request->class_id, fn ($query, $id) => $query->where('class_id', $id))
            // Emploi du temps d'un enseignant : ses cours dans toutes ses classes.
            ->when($request->teacher_id, fn ($query, $id) => $query->whereHas('assignment', fn ($q) => $q->where('teacher_id', $id)))
            ->where('is_active', true)
            ->orderBy('day_of_week')->orderBy('starts_at')->get();

        // Créneaux saisis avant le contrôle des chevauchements : on les signale
        // pour que le directeur les corrige.
        $all = ClassSchedule::with('assignment:id,teacher_id')->where('is_active', true)->get();
        foreach ($rows as $row) {
            $clash = $all->first(fn (ClassSchedule $other) => $other->id !== $row->id
                && $other->day_of_week === $row->day_of_week
                && $other->starts_at < $row->ends_at && $other->ends_at > $row->starts_at
                && ($other->class_id === $row->class_id || ($row->assignment && $other->assignment?->teacher_id === $row->assignment->teacher_id)));
            $row->setAttribute('conflict', $clash === null ? null : ($clash->class_id === $row->class_id ? 'La classe a un autre cours à cette heure.' : 'L\'enseignant a un autre cours à cette heure.'));
        }

        return $rows;
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
            // Changer d'enseignant : une autre affectation de la même classe et matière.
            'teacher_assignment_id' => ['sometimes', \App\Support\SchoolRule::exists('teacher_assignments')],
        ]);

        $assignment = isset($data['teacher_assignment_id']) ? \Modules\Teachers\Models\TeacherAssignment::findOrFail($data['teacher_assignment_id']) : $schedule->assignment;
        abort_if($assignment && ($assignment->class_id !== $schedule->class_id || $assignment->subject_id !== $schedule->subject_id), 422, 'Cette affectation ne correspond pas à la classe et à la matière du créneau.');

        // Déplacer un créneau peut créer le même chevauchement qu'en créer un.
        $merged = [...$schedule->only(['day_of_week', 'starts_at', 'ends_at']), ...$data];

        if ($data['is_active'] ?? $schedule->is_active) {
            $this->assertFree(
                $schedule->class_id,
                $assignment?->teacher_id,
                (int) $merged['day_of_week'],
                substr((string) $merged['starts_at'], 0, 5),
                substr((string) $merged['ends_at'], 0, 5),
                ignoreId: $schedule->id,
            );
        }

        $schedule->update($data);

        return $schedule->fresh(['schoolClass:id,label', 'subject:id,label', 'assignment.teacher:id,full_name']);
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
