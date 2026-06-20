<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'product_id'           => $this->product_id,
            'rating'               => $this->rating,
            'title'                => $this->title,
            'content'              => $this->content,
            'status'               => $this->status,
            'is_verified_purchase' => $this->is_verified_purchase,
            'author'               => $this->user?->name ?? 'Client',
            'product'              => $this->when(
                $this->relationLoaded('product') && $this->product,
                fn () => [
                    'id'   => $this->product->id,
                    'name' => $this->product->name,
                ]
            ),
            'created_at' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
