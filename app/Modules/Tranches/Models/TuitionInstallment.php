<?php

namespace Modules\Tranches\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SchoolClasses\Models\SchoolClass;

class TuitionInstallment extends Model
{
    protected $table = 'tuition_installments';

    protected $fillable = ['class_id', 'academic_year_id', 'label', 'amount', 'due_date'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
