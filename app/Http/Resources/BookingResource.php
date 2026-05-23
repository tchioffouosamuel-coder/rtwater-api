<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'notes'        => $this->notes,

            'scheduled_at' => $this->scheduled_at->format('d/m/Y à H:i'),
            // scheduled_at est déjà un objet Carbon (casté dans le Model)
            // "15/03/2024 à 14:30"

            'is_upcoming'  => $this->scheduled_at->isFuture(),
            // isFuture() → méthode Carbon
            // true si la date du RDV est dans le futur
            // false si le RDV est passé

            'service'      => new ServiceResource($this->whenLoaded('service')),
            'user'         => new UserResource($this->whenLoaded('user')),

            'created_at'   => $this->created_at->format('d/m/Y'),
        ];
    }
}
