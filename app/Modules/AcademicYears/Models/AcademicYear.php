<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'label', 'is_active', 'date_start', 'date_end'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_start' => 'date',
            'date_end' => 'date',
        ];
    }
}
