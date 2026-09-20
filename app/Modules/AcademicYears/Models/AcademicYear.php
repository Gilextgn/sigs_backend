<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = ['school_id', 'code', 'label', 'is_active', 'date_start', 'date_end', 'closed_at', 'closed_by_user_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_start' => 'date',
            'date_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }
}
