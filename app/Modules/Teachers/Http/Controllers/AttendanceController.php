<?php

namespace Modules\Teachers\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Teachers\Models\ClassSchedule;
use Modules\Teachers\Models\TeacherAttendance;
use Modules\Teachers\Models\TeachingSession;

class AttendanceController extends Controller
{
    public function sessions(Request $request)
    {
        return TeachingSession::with(['assignment.teacher:id,full_name', 'schoolClass:id,label', 'assignment.subject:id,label', 'attendance'])
            ->when($request->date, fn ($query, $date) => $query->whereDate('session_date', $date))
            ->orderByDesc('session_date')->orderBy('starts_at')->get();
    }

    public function index(Request $request)
    {
        return TeacherAttendance::with(['session.assignment.teacher:id,full_name', 'session.schoolClass:id,label', 'session.assignment.subject:id,label'])
            ->when($request->date, fn ($query, $date) => $query->whereHas('session', fn ($session) => $session->whereDate('session_date', $date)))
            ->orderByDesc('created_at')->get();
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $target = Carbon::parse($data['date']);
        $dayOfWeek = $target->dayOfWeekIso;
        $created = 0;
        $existing = 0;

        ClassSchedule::with('assignment')
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get()
            ->each(function (ClassSchedule $schedule) use ($target, &$created, &$existing) {
                $exists = TeachingSession::where('teacher_assignment_id', $schedule->teacher_assignment_id)
                    ->whereDate('session_date', $target)
                    ->where('starts_at', $schedule->starts_at)
                    ->exists();

                if ($exists) {
                    $existing++;

                    return;
                }

                TeachingSession::create([
                    'school_id' => 1,
                    'academic_year_id' => $schedule->academic_year_id,
                    'class_id' => $schedule->class_id,
                    'subject_id' => $schedule->subject_id,
                    'teacher_assignment_id' => $schedule->teacher_assignment_id,
                    'session_date' => $target->toDateString(),
                    'starts_at' => $schedule->starts_at,
                    'ends_at' => $schedule->ends_at,
                    'planned_minutes' => Carbon::parse($schedule->starts_at)->diffInMinutes(Carbon::parse($schedule->ends_at)),
                ]);

                $created++;
            });

        $activeSchedulesCount = ClassSchedule::where('day_of_week', $dayOfWeek)->where('is_active', true)->count();

        return response()->json([
            'date' => $target->toDateString(),
            'created' => $created,
            'existing' => $existing,
            'active_schedules_count' => $activeSchedulesCount,
            'message' => $created > 0
                ? "{$created} séance(s) générée(s) pour {$target->toDateString()}."
                : ($activeSchedulesCount > 0
                    ? "Les séances pour {$target->toDateString()} existent déjà. Aucune duplication n’a été créée."
                    : "Aucun cours actif n’a été trouvé pour le jour {$target->translatedFormat('l')} ({$target->toDateString()}). Ajoutez un emploi du temps pour cette date avant de générer les séances."),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'teaching_session_id' => ['required', 'exists:teaching_sessions,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'status' => ['required', 'in:present,absent,justified,replaced'],
            'absence_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string'],
            'replacement_teacher_id' => ['nullable', 'exists:teachers,id'],
        ]);

        $session = TeachingSession::findOrFail($data['teaching_session_id']);
        abort_unless($session->assignment->teacher_id === (int) $data['teacher_id'], 422, 'Cet enseignant ne correspond pas à la séance.');

        $absenceMinutes = min((int) ($data['absence_minutes'] ?? 0), $session->planned_minutes);

        if (($data['status'] ?? null) === 'replaced' && ! empty($data['replacement_teacher_id'])) {
            $realizedMinutes = $session->planned_minutes;
        } elseif (in_array($data['status'], ['present', 'justified'], true)) {
            $realizedMinutes = max(0, $session->planned_minutes - $absenceMinutes);
        } else {
            $realizedMinutes = 0;
        }

        $session->update([
            'realized_minutes' => $realizedMinutes,
            'status' => 'completed',
        ]);

        return TeacherAttendance::updateOrCreate(
            ['teaching_session_id' => $session->id, 'teacher_id' => $data['teacher_id']],
            $data,
        );
    }

    public function createSession(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_assignment_id' => ['required', 'exists:teacher_assignments,id'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'planned_minutes' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(TeachingSession::create($data + ['school_id' => 1]), 201);
    }
}
