<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'quantity' => $this->quantity,

            'product'  => new ProductResource($this->whenLoaded('product')),
            // Le produit complet formaté par ProductResource

            'subtotal' => $this->whenLoaded('product')
                ? (float) $this->getSubtotalAttribute()
                : null,
            // Le sous-total (quantity × price)
            // Seulement si le produit est chargé
            // car on a besoin de product.price pour calculer

            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
