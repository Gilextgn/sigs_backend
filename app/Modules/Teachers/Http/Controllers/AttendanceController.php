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
        $sessions = TeachingSession::with(['assignment.teacher:id,full_name', 'schoolClass:id,label', 'assignment.subject:id,label', 'attendance'])
            ->when($request->date, fn ($query, $date) => $query->whereDate('session_date', $date))
            ->orderByDesc('session_date')->orderBy('starts_at')->get();

        // Un enseignant planifié dans deux classes à la même heure (créneaux saisis
        // avant le contrôle) : on le signale, il n'a pu faire qu'un des deux cours.
        foreach ($sessions as $session) {
            $clash = $sessions->first(fn (TeachingSession $other) => $this->overlaps($session, $other));
            $session->setAttribute('conflict', $clash ? 'Même enseignant en '.($clash->schoolClass?->label ?? 'autre classe').' à la même heure : un seul des deux cours a pu être fait.' : null);
        }

        return $sessions;
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
                    'school_id' => \App\Support\CurrentSchool::id(),
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
            'teaching_session_id' => ['required', \App\Support\SchoolRule::exists('teaching_sessions')],
            'teacher_id' => ['required', \App\Support\SchoolRule::exists('teachers')],
            // present : cours fait ; late : fait avec du retard (minutes déduites) ;
            // absent : non fait, non payé ; justified : absence excusée (motif obligatoire).
            // « replaced » n'est plus proposé : les anciennes saisies restent lisibles.
            'status' => ['required', 'in:present,late,absent,justified'],
            'absence_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],

        ]);

        $session = TeachingSession::findOrFail($data['teaching_session_id']);
        abort_unless($session->assignment->teacher_id === (int) $data['teacher_id'], 422, 'Cet enseignant ne correspond pas à la séance.');
        abort_if($data['status'] === 'late' && (int) ($data['absence_minutes'] ?? 0) <= 0, 422, 'Indiquez le nombre de minutes de retard.');
        abort_if($data['status'] === 'justified' && blank($data['reason'] ?? null), 422, 'Indiquez le motif de l’absence justifiée.');

        // Les minutes ne comptent que pour un retard : ailleurs elles induiraient en erreur.
        $data['absence_minutes'] = $data['status'] === 'late' ? min((int) $data['absence_minutes'], $session->planned_minutes) : 0;
        $data['replacement_teacher_id'] = null;

        // Il ne peut pas être payé pour deux cours donnés en même temps.
        if (in_array($data['status'], ['present', 'late', 'justified'], true)) {
            $others = TeachingSession::with(['attendance', 'schoolClass:id,label', 'assignment'])
                ->whereDate('session_date', $session->session_date)->where('id', '!=', $session->id)->get();
            $paidClash = $others->first(fn (TeachingSession $other) => $this->overlaps($session, $other)
                && in_array($other->attendance?->status, ['present', 'late', 'justified'], true));
            abort_if($paidClash !== null, 422, 'Cet enseignant est déjà noté présent en '.($paidClash?->schoolClass?->label ?? 'une autre classe').' à la même heure : notez ce cours-ci Absent ou Remplacé, et corrigez l’emploi du temps.');
        }

        $realizedMinutes = match ($data['status']) {
            'present', 'justified' => $session->planned_minutes,
            'late' => max(0, $session->planned_minutes - $data['absence_minutes']),
            default => 0,
        };

        $session->update([
            'realized_minutes' => $realizedMinutes,
            'status' => 'completed',
        ]);

        return TeacherAttendance::updateOrCreate(
            ['teaching_session_id' => $session->id, 'teacher_id' => $data['teacher_id']],
            $data,
        );
    }

    private function overlaps(TeachingSession $a, TeachingSession $b): bool
    {
        return $a->id !== $b->id
            && $a->session_date?->toDateString() === $b->session_date?->toDateString()
            && $a->assignment && $b->assignment
            && $a->assignment->teacher_id === $b->assignment->teacher_id
            && $a->starts_at < $b->ends_at && $b->starts_at < $a->ends_at;
    }

    public function createSession(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', \App\Support\SchoolRule::exists('academic_years')],
            'class_id' => ['required', \App\Support\SchoolRule::exists('classes')],
            'subject_id' => ['required', \App\Support\SchoolRule::exists('subjects')],
            'teacher_assignment_id' => ['required', \App\Support\SchoolRule::exists('teacher_assignments')],
            'session_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'planned_minutes' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(TeachingSession::create($data), 201);
    }
}
