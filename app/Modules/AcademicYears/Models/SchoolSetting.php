<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use \App\Support\BelongsToSchool;

    protected $fillable = ['school_id', 'setting_key', 'setting_value'];
}
