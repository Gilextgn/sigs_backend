<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'actor_user_id', 'action_code', 'entity_name',
        'entity_id', 'entity_label', 'details_json', 'changes_json', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'details_json' => 'array',
            'changes_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(\App\Models\User::class, 'actor_user_id');
    }

    public static function record(string $actionCode, string $entityName, ?string $entityId = null, array $details = []): self
    {
        return self::create([
            'school_id' => \App\Support\CurrentSchool::id(),
            'actor_user_id' => request()->user()?->id,
            'action_code' => $actionCode,
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'details_json' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
