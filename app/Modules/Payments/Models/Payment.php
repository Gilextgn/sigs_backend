<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Students\Models\Student;

class Payment extends Model
{
    use SoftDeletes, \App\Support\BelongsToSchool;

    public $timestamps = false; // uniquement created_at, géré manuellement (pas de update après création)

    protected $fillable = [
        'school_id', 'academic_year_id', 'reference_code', 'student_id',
        'cashier_user_id', 'payment_date', 'total_paid_amount', 'verification_token',
    ];

    // Ne sert qu'à la vérification publique (QR code du reçu) : exposé seulement par show().
    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'total_paid_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function cashier()
    {
        return $this->belongsTo(\App\Models\User::class, 'cashier_user_id');
    }

    public function items()
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function deliveries()
    {
        return $this->hasMany(ReceiptDelivery::class)->orderByDesc('id');
    }
}
