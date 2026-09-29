<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'créneau';

    public function auditLabel(): string
    {
        return ($this->subject?->label ?? 'cours').' — '.($this->schoolClass?->label ?? '');
    }

    protected $fillable = ['school_id', 'academic_year_id', 'class_id', 'subject_id', 'teacher_assignment_id', 'day_of_week', 'starts_at', 'ends_at', 'room', 'is_active'];

    protected function casts(): array { return ['day_of_week' => 'integer', 'is_active' => 'boolean']; }

    public function assignment() { return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id'); }
    public function schoolClass() { return $this->belongsTo(\Modules\SchoolClasses\Models\SchoolClass::class, 'class_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
}
