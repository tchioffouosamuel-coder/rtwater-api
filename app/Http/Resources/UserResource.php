<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,

            'roles'      => RoleResource::collection($this->whenLoaded('roles')),
            // RoleResource::collection() → formate une LISTE de roles
            // chaque role est formaté par RoleResource::toArray()
            //
            // $this->whenLoaded('roles')
            // → "inclus les roles SEULEMENT s'ils ont été chargés"
            // → évite les requêtes SQL inutiles
            //
            // Si on a fait : User::with('roles')->find(1)
            // → les roles sont chargés → inclus dans la réponse
            //
            // Si on a fait : User::find(1)
            // → les roles ne sont pas chargés → champ absent de la réponse
            // → pas de requête SQL supplémentaire

            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
