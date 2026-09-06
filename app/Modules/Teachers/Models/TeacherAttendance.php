<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model
{
    protected $fillable = ['teaching_session_id', 'teacher_id', 'status', 'absence_minutes', 'reason', 'replacement_teacher_id'];

    protected function casts(): array { return ['absence_minutes' => 'integer']; }

    public function session() { return $this->belongsTo(TeachingSession::class, 'teaching_session_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
}
