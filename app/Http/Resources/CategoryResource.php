<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            // 'product' ou 'service'

            'products_count' => $this->whenCounted('products'),
            // whenCounted() → inclus le comptage SEULEMENT
            // si on a utilisé withCount('products') dans la requête
            //
            // Utilisation dans le controller :
            // Category::withCount('products')->get()
            // → retourne { ..., "products_count": 12 }
            //
            // Sans withCount :
            // → products_count est absent de la réponse

            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
