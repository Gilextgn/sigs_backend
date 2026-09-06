<?php

namespace Modules\SchoolClasses\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCycle extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'label', 'sort_order'];

    public function classes()
    {
        return $this->hasMany(SchoolClass::class, 'cycle_id');
    }
}
