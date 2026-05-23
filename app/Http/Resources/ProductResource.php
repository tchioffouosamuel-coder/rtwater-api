<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => (float) $this->price,
            // (float) → cast en nombre décimal PHP
            // Évite que price soit retourné comme string "999.00"
            // et retourne plutôt le nombre 999.0

            'stock'       => $this->stock,
            'is_active'   => $this->is_active,

            'image_url'   => $this->image
                ? asset('storage/' . $this->image)
                : null,
            // asset() → génère l'URL complète vers le fichier
            // "products/iphone.jpg" → "http://127.0.0.1:8000/storage/products/iphone.jpg"
            //
            // Opérateur ternaire :
            // $this->image ? ... : null
            // Si image existe → génère l'URL
            // Si image est null → retourne null

            'category'    => new CategoryResource($this->whenLoaded('category')),
            // new CategoryResource() → formate UN seul objet Category
            // $this->whenLoaded('category') → seulement si chargé

            'created_at'  => $this->created_at->format('d/m/Y'),
        ];
    }
}
