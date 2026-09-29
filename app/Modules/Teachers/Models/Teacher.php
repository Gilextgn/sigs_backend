<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'enseignant';

    /** Champs chiffrés : jamais recopiés en clair dans le journal. */
    protected array $auditHidden = ['full_name', 'phone'];

    protected $fillable = ['school_id', 'full_name', 'phone', 'subject', 'level', 'pay_mode', 'monthly_salary', 'hourly_rate', 'status'];

    /** Payé à l'heure faite (par défaut), plutôt qu'au salaire fixe. */
    public function isPaidHourly(): bool
    {
        return ($this->pay_mode ?? 'hourly') !== 'monthly';
    }

    protected function casts(): array
    {
        return [
            'full_name' => 'encrypted',
            'phone' => 'encrypted',
            'monthly_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
