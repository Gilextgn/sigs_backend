<?php

namespace Modules\Teachers\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Teachers\Models\TeacherAssignment;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request)
    {
        return TeacherAssignment::with(['teacher:id,full_name', 'schoolClass:id,label', 'subject:id,label'])
            ->when($request->teacher_id, fn ($query, $id) => $query->where('teacher_id', $id))
            ->when($request->class_id, fn ($query, $id) => $query->where('class_id', $id))
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'hourly_rate' => ['required', 'numeric', 'min:0.01'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $assignment = TeacherAssignment::firstOrCreate(
            [
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'teacher_id' => $data['teacher_id'],
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
            ],
            $data + ['school_id' => 1],
        );

        return response()->json($assignment->load(['teacher:id,full_name', 'schoolClass:id,label', 'subject:id,label']), $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, TeacherAssignment $teacherAssignment)
    {
        $data = $request->validate([
            'hourly_rate' => ['sometimes', 'numeric', 'min:0.01'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $teacherAssignment->update($data);

        return $teacherAssignment->fresh(['teacher:id,full_name', 'schoolClass:id,label', 'subject:id,label']);
    }

    public function destroy(TeacherAssignment $teacherAssignment)
    {
        $teacherAssignment->update(['is_active' => false]);

        return response()->noContent();
    }
}
