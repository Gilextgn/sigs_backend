<?php

namespace Modules\Debtors\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'channel', 'recipient', 'status', 'error',
        'amount', 'message', 'sent_by_user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by_user_id');
    }
}
