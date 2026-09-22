<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/** Un paiement d'abonnement reçu d'une école (argent de la plateforme). */
class SchoolPayment extends Model
{
    public const METHODS = ['mobile_money', 'cash', 'bank', 'other'];

    protected $fillable = [
        'school_id', 'amount', 'paid_at', 'months', 'due_before', 'due_after',
        'method', 'reference', 'note', 'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'months' => 'integer',
            'paid_at' => 'date',
            'due_before' => 'date',
            'due_after' => 'date',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'recorded_by_user_id');
    }
}
