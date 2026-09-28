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
            'academic_year_id' => ['nullable', \App\Support\SchoolRule::exists('academic_years')],
            'teacher_id' => ['required', \App\Support\SchoolRule::exists('teachers')],
            'class_id' => ['required', \App\Support\SchoolRule::exists('classes')],
            'subject_id' => ['required', \App\Support\SchoolRule::exists('subjects')],
            // Vide : le tarif horaire saisi sur la fiche de l'enseignant (0 pour un salaire fixe).
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $teacher = \Modules\Teachers\Models\Teacher::findOrFail($data['teacher_id']);
        $data['hourly_rate'] = $data['hourly_rate'] ?? ($teacher->isPaidHourly() ? (float) $teacher->hourly_rate : 0);
        abort_if($teacher->isPaidHourly() && $data['hourly_rate'] <= 0, 422, 'Indiquez le tarif horaire de cet enseignant (sur sa fiche ou ici).');

        $assignment = TeacherAssignment::firstOrCreate(
            [
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'teacher_id' => $data['teacher_id'],
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
            ],
            $data,
        );

        return response()->json($assignment->load(['teacher:id,full_name', 'schoolClass:id,label', 'subject:id,label']), $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, TeacherAssignment $teacherAssignment)
    {
        $data = $request->validate([
            'hourly_rate' => ['sometimes', 'numeric', 'min:0'],
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
