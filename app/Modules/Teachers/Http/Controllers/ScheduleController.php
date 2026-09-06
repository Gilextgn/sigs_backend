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
            ->where('is_active', true)
            ->orderBy('day_of_week')->orderBy('starts_at')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_assignment_id' => ['required', 'exists:teacher_assignments,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:80'],
        ]);

        $assignment = \Modules\Teachers\Models\TeacherAssignment::findOrFail($data['teacher_assignment_id']);
        abort_unless($assignment->class_id === (int) $data['class_id'] && $assignment->subject_id === (int) $data['subject_id'], 422, 'Cette affectation ne correspond pas à la classe et à la matière choisies.');

        $overlap = ClassSchedule::where('class_id', $data['class_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->exists();
        abort_if($overlap, 422, 'Cette classe a déjà un cours sur ce créneau.');

        return response()->json(ClassSchedule::create($data + ['school_id' => 1]), 201);
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
        $schedule->update($data);

        return $schedule->fresh();
    }

    public function destroy(ClassSchedule $schedule)
    {
        $schedule->update(['is_active' => false]);

        return response()->noContent();
    }
}
