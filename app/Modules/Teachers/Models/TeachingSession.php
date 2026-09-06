<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;

class TeachingSession extends Model
{
    protected $fillable = ['school_id', 'academic_year_id', 'class_id', 'subject_id', 'teacher_assignment_id', 'session_date', 'starts_at', 'ends_at', 'planned_minutes', 'realized_minutes', 'status', 'notes'];

    protected function casts(): array { return ['session_date' => 'date', 'planned_minutes' => 'integer', 'realized_minutes' => 'integer']; }

    public function assignment() { return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id'); }
    public function schoolClass() { return $this->belongsTo(\Modules\SchoolClasses\Models\SchoolClass::class, 'class_id'); }
    public function attendance() { return $this->hasOne(TeacherAttendance::class); }
}
