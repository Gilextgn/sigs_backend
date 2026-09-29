<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SchoolClasses\Models\SchoolClass;

class TeacherAssignment extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'affectation';

    public function auditLabel(): string
    {
        return ($this->subject?->label ?? 'matière').' — '.($this->schoolClass?->label ?? 'classe');
    }

    protected $fillable = ['school_id', 'academic_year_id', 'teacher_id', 'class_id', 'subject_id', 'hourly_rate', 'weekly_hours', 'is_active'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:2', 'weekly_hours' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function schedules() { return $this->hasMany(ClassSchedule::class); }
    public function sessions() { return $this->hasMany(TeachingSession::class); }
}
