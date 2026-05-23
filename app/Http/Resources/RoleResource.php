<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
// JsonResource = classe de base de Laravel pour les resources
// $this = l'objet Role qu'on formate
{
    public function toArray(Request $request): array
    // toArray() = définit la structure JSON retournée
    // Request $request = la requête HTTP entrante
    // (utile si on veut adapter la réponse selon l'utilisateur connecté)
    {
        return [
            'id'          => $this->id,
            // $this->id → accède à la propriété id du Model Role
            // Eloquent mappe automatiquement les colonnes DB
            // en propriétés de l'objet

            'name'        => $this->name,
            // Le nom technique : 'admin', 'gestionnaire'...

            'label'       => $this->label,
            // Le nom lisible : 'Administrateur', 'Gestionnaire'...

            'description' => $this->description,
            // Description du rôle

            'is_default'  => $this->is_default,
            // true ou false (déjà casté en boolean par le Model)

            'is_predefined' => $this->isPredefined(),
            // On appelle la méthode du Model
            // true si c'est un rôle système (admin, user, etc.)
            // false si c'est un rôle créé par l'admin

            'created_at'  => $this->created_at->format('d/m/Y'),
            // ->format('d/m/Y') → formate la date Carbon
            // "2024-01-15T14:30:00.000000Z" → "15/01/2024"
        ];
    }
}
