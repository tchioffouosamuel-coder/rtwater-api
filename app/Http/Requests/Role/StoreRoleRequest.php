<?php

namespace App\Http\Requests\Role;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'name'        => 'required|string|unique:roles,name|max:50',
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du rôle est obligatoire',
            'name.unique'   => 'Ce nom de rôle existe déjà',
            'label.required' => 'Le label est obligatoire',
        ];
    }
}
