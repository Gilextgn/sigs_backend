<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['school_id', 'action', 'reason', 'actor_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id');
    }
}
