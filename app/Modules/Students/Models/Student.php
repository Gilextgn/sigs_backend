<?php

namespace Modules\Students\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\SchoolClasses\Models\SchoolClass;

class Student extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'élève';

    /** Champs chiffrés : jamais recopiés en clair dans le journal. */
    protected array $auditHidden = ['first_name', 'last_name'];

    public function auditLabel(): string
    {
        return $this->fullName().' ('.$this->matricule.')';
    }

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'guardian_id',
        'registration_year', 'registration_sequence', 'matricule',
        'first_name', 'last_name', 'birth_date', 'gender', 'status',
    ];

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'last_name' => 'encrypted',
            'birth_date' => 'date',
        ];
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function payments()
    {
        return $this->hasMany(\Modules\Payments\Models\Payment::class);
    }

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
