<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'quantity' => $this->quantity,
            'price'    => (float) $this->price,
            // Prix au moment de l'achat (pas le prix actuel du produit)

            'subtotal' => (float) $this->getSubtotalAttribute(),
            // quantity × price

            'product'  => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
