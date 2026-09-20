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
            'student_id' => ['required', \App\Support\SchoolRule::exists('students')],
            // un paiement peut contenir plusieurs lignes : tranches + autres frais
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:TRANCHE,AUTRE_FRAIS'],
            'items.*.tuition_installment_id' => ['required_if:items.*.item_type,TRANCHE', 'nullable', \App\Support\SchoolRule::exists('tuition_installments')],
            'items.*.fee_type_id' => ['required_if:items.*.item_type,AUTRE_FRAIS', 'nullable', \App\Support\SchoolRule::exists('fee_types')],
            'items.*.paid_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
