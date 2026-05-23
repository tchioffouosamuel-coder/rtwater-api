<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'description'      => $this->description,
            'price'            => (float) $this->price,
            'duration_minutes' => $this->duration_minutes,

            'duration_label'   => $this->duration_minutes . ' minutes',
            // Propriété calculée : "60 minutes"
            // Concaténation avec . en PHP (pas + comme en JS)

            'is_available'     => $this->is_available,
            'category'         => new CategoryResource($this->whenLoaded('category')),
            'created_at'       => $this->created_at->format('d/m/Y'),
        ];
    }
}
