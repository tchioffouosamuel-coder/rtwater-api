<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id'   => 'required|exists:services,id',
            'scheduled_at' => 'required|date|after:now',
            // after:now → la date doit être dans le futur
            'notes'        => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required'   => 'Le service est obligatoire',
            'service_id.exists'     => 'Le service sélectionné n\'existe pas',
            'scheduled_at.required' => 'La date du rendez-vous est obligatoire',
            'scheduled_at.date'     => 'La date n\'est pas valide',
            'scheduled_at.after'    => 'La date doit être dans le futur',
        ];
    }
}
