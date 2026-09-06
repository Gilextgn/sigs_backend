<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'label'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
