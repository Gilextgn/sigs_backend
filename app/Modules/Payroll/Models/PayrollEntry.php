<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Teachers\Models\Teacher;

class PayrollEntry extends Model
{
    use \App\Support\BelongsToSchool;

    protected $fillable = ['school_id', 'teacher_id', 'period', 'base_amount', 'bonus_amount', 'deduction_amount', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function netAmount(): float
    {
        return (float) $this->base_amount + (float) $this->bonus_amount - (float) $this->deduction_amount;
    }
}
