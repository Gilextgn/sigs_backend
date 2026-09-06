<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    protected $fillable = ['school_id', 'setting_key', 'setting_value'];
}
