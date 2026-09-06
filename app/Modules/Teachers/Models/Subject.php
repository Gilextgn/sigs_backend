<?php

namespace Modules\Teachers\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['school_id', 'code', 'label', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
