<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user    = $this->user();
        $booking = $this->route('booking');
        // $this->route('booking') → l'objet Booking injecté par Route Model Binding

        if ($user->isAdmin()) {
            // L'admin peut changer n'importe quel statut
            return [
                'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
                'notes'  => 'sometimes|nullable|string|max:500',
            ];
        }

        // Le client ne peut qu'annuler
        return [
            'status' => 'required|in:cancelled',
            'notes'  => 'sometimes|nullable|string|max:500',
        ];
    }
}
