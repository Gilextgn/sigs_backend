<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use \App\Support\BelongsToSchool;

    protected $fillable = ['school_id', 'code', 'label', 'level', 'is_active'];

    /** Matières d'un niveau : les siennes et celles communes aux deux. */
    public function scopeForLevel($query, string $level)
    {
        return $query->whereIn('level', [$level, 'both']);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
