<?php

namespace Modules\Students\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'status' => $this->status,
            'class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass->id,
                'label' => $this->schoolClass->label,
                'tuition_amount' => $this->schoolClass->tuition_amount,
            ]),
            'guardian' => $this->whenLoaded('guardian', fn () => [
                'id' => $this->guardian->id,
                'full_name' => $this->guardian->full_name,
                'relationship_label' => $this->guardian->relationship_label,
                'phone' => $this->guardian->phone,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
