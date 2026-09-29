<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;

/** Remise de caisse d'un caissier au directeur (voir la migration). */
class CashHandover extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'cashier_user_id', 'received_by_user_id', 'to_payment_id', 'payment_count',
        'expected_amount', 'received_amount', 'difference', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /** Dernier paiement déjà remis par ce caissier (0 s'il n'a jamais rien remis). */
    public static function lastRemittedPaymentId(int $cashierUserId): int
    {
        return (int) static::where('cashier_user_id', $cashierUserId)->max('to_payment_id');
    }

    public function cashier()
    {
        return $this->belongsTo(\App\Models\User::class, 'cashier_user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(\App\Models\User::class, 'received_by_user_id');
    }
}
