<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'subtotal'       => (float) $this->subtotal,
            'shipping_fee'   => (float) $this->shipping_fee,
            'discount_total' => (float) $this->discount_total,
            'discount_code'  => $this->discount_code,
            'total'          => (float) $this->total,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'address'        => $this->address,
            'admin_notes'    => $this->admin_notes,
            'shipping_zone'  => $this->whenLoaded('shippingZone', fn() => [
                'id'   => $this->shippingZone->id,
                'name' => $this->shippingZone->name,
            ]),

            'user'       => new UserResource($this->whenLoaded('user')),
            // L'utilisateur qui a passé la commande

            'items'      => OrderItemResource::collection($this->whenLoaded('items')),
            // La liste des articles de la commande
            // chaque article formaté par OrderItemResource

            'created_at' => $this->created_at->format('d/m/Y à H:i'),
            // "15/01/2024 à 14:30"
            // H:i → heures:minutes en format 24h
        ];
    }
}
