<?php

namespace App\Http\Requests\Service;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Permissions::userCan($this->user(), Permissions::MANAGE_SERVICES);
        // Aligne sur la permission exigee par la route. Exiger isAdmin() ici
        // contredisait le middleware : un gestionnaire passait la route puis
        // se faisait refuser par le FormRequest.
    }

    public function rules(): array
    {
        return [
            'category_id'      => 'sometimes|exists:categories,id',
            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'is_available'     => 'sometimes|boolean',
        ];
    }
}
