<?php

namespace Modules\SchoolClasses\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tranches\Models\TuitionInstallment;

class SchoolClass extends Model
{
    use \App\Support\BelongsToSchool;

    protected $table = 'classes';

    protected $fillable = [
        'school_id', 'academic_year_id', 'cycle_id', 'parent_class_id', 'code', 'label',
        'tuition_amount', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tuition_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Ordre pédagogique réglé par le directeur (écran Classes). */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    public function cycle()
    {
        return $this->belongsTo(SchoolCycle::class, 'cycle_id');
    }

    /**
     * Classe qui porte les tarifs : elle-même, ou sa classe principale si
     * c'est un groupe (CE1 B reprend scolarité, tranches et frais de CE1 A).
     */
    public function getPricingClassIdAttribute(): int
    {
        return (int) ($this->parent_class_id ?? $this->id);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_class_id');
    }

    public function groups()
    {
        return $this->hasMany(self::class, 'parent_class_id');
    }

    /** Tranches applicables, héritées de la classe principale pour un groupe. */
    public function installments()
    {
        return $this->hasMany(TuitionInstallment::class, 'class_id', 'pricing_class_id');
    }

    /** Tranches enregistrées sur cette classe même (toujours vide pour un groupe). */
    public function ownInstallments()
    {
        return $this->hasMany(TuitionInstallment::class, 'class_id');
    }

    /** Ramène une liste de classes à celles qui portent les tarifs. */
    public static function pricingIds(array $classIds): array
    {
        return static::whereIn('id', $classIds)->get(['id', 'parent_class_id'])
            ->map(fn (self $class) => $class->pricing_class_id)
            ->unique()->values()->all();
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
