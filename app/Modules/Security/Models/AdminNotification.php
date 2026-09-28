<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'user_id', 'actor_user_id', 'action_code', 'title', 'body',
        'entity_name', 'entity_id', 'read_at', 'created_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id');
    }
}
