<?php

namespace Modules\SchoolClasses\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tranches\Models\TuitionInstallment;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'school_id', 'academic_year_id', 'cycle_id', 'code', 'label',
        'tuition_amount', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tuition_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function cycle()
    {
        return $this->belongsTo(SchoolCycle::class, 'cycle_id');
    }

    public function installments()
    {
        return $this->hasMany(TuitionInstallment::class, 'class_id');
    }

    public function students()
    {
        return $this->hasMany(\Modules\Students\Models\Student::class, 'class_id');
    }

    public function teacherAssignments()
    {
        return $this->hasMany(\Modules\Teachers\Models\TeacherAssignment::class, 'class_id');
    }

    /**
     * Somme des tranches déjà définies pour cette classe.
     */
    public function installmentsTotal(): float
    {
        return (float) $this->installments()->sum('amount');
    }
}
