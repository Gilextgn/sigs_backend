<?php

namespace Modules\Students\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\SchoolClasses\Models\SchoolClass;

class StudentEnrollment extends Model
{
    protected $fillable = ['student_id', 'academic_year_id', 'class_id', 'enrolled_by_user_id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
