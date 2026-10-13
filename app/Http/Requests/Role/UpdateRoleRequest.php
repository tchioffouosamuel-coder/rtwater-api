<?php

namespace App\Http\Requests\Role;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Permissions::userCan($this->user(), Permissions::MANAGE_USERS);
        // Aligne sur la permission exigee par la route. Exiger isAdmin() ici
        // contredisait le middleware : un gestionnaire passait la route puis
        // se faisait refuser par le FormRequest.
    }

    public function rules(): array
    {
        return [
            'label'       => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:255',
        ];
    }
}
