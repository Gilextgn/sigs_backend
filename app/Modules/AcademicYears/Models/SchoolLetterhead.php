<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

/** Image d'en-tête d'une école, gardée en base pour survivre aux redéploiements. */
class SchoolLetterhead extends Model
{
    use \App\Support\BelongsToSchool;

    protected $fillable = ['school_id', 'mime_type', 'data'];
}
