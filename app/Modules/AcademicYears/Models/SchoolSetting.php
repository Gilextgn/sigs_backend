<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'paramètre';

    public function auditLabel(): string
    {
        return $this->setting_key;
    }

    protected $fillable = ['school_id', 'setting_key', 'setting_value'];
}
