<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            // un paiement peut contenir plusieurs lignes : tranches + autres frais
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:TRANCHE,AUTRE_FRAIS'],
            'items.*.tuition_installment_id' => ['required_if:items.*.item_type,TRANCHE', 'nullable', 'exists:tuition_installments,id'],
            'items.*.fee_type_id' => ['required_if:items.*.item_type,AUTRE_FRAIS', 'nullable', 'exists:fee_types,id'],
            'items.*.paid_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
