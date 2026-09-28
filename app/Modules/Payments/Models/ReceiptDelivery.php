<?php

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;

/** Un envoi du reçu au parent (mail ou WhatsApp), réussi ou non. */
class ReceiptDelivery extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = ['school_id', 'payment_id', 'channel', 'recipient', 'status', 'error', 'triggered_by_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
