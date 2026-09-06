<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Fees\Models\FeeType;
use Modules\Tranches\Models\TuitionInstallment;

class PaymentItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'item_type', 'tuition_installment_id', 'fee_type_id',
        'expected_amount', 'paid_amount',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function tuitionInstallment()
    {
        return $this->belongsTo(TuitionInstallment::class);
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }

    public function label(): string
    {
        return $this->item_type === 'TRANCHE'
            ? $this->tuitionInstallment?->label ?? 'Tranche'
            : $this->feeType?->label ?? 'Frais';
    }
}
