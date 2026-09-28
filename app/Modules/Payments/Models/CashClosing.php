<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'cashier_user_id', 'closing_date', 'payment_count', 'expected_amount',
        'counted_amount', 'difference', 'note', 'closed_at',
        'reopened_at', 'reopened_by_user_id', 'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date:Y-m-d',
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /** Clôture en vigueur (une clôture rouverte reste en historique). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('reopened_at');
    }

    public static function isClosed(int $cashierUserId, string $date): bool
    {
        return static::active()->where('cashier_user_id', $cashierUserId)->whereDate('closing_date', $date)->exists();
    }

    public function cashier()
    {
        return $this->belongsTo(\App\Models\User::class, 'cashier_user_id');
    }

    public function reopenedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'reopened_by_user_id');
    }
}
