<?php

namespace Modules\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SchoolClasses\Models\SchoolClass;

class FeeType extends Model
{
    protected $fillable = ['code', 'label', 'category', 'amount', 'is_active', 'is_mandatory'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
        ];
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'fee_type_classes', 'fee_type_id', 'class_id');
    }
}
