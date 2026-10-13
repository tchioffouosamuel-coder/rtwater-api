<?php

namespace App\Http\Resources;

use App\Support\OrderPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'sku'          => $this->sku,
            'price'        => (float) $this->price,

            // Remise effective : une promotion expiree ne doit plus s'afficher
            'discount'     => $this->activeDiscount(),
            'final_price'  => OrderPricing::unitPrice($this->resource),
            'discount_ends_at' => $this->discount_ends_at?->toIso8601String(),

            'stock'        => $this->stock,
            'is_available' => $this->is_available,

            'image_url'    => $this->image_url
                ? asset('storage/' . $this->image_url)
                : null,

            // Nom de photo declare dans l'Excel et pas encore televerse.
            // Renseigne uniquement tant que le produit n'a pas d'image.
            'awaiting_image' => $this->image_url === null ? $this->image_filename : null,

            // Fiche technique : « type » dit comment l'afficher, les autres
            // champs sont nuls selon le cas.
            'datasheet'    => $this->datasheet_type ? [
                'type'     => $this->datasheet_type,
                'text'     => $this->datasheet_type === 'text' ? $this->datasheet_text : null,
                'url'      => $this->datasheet_path ? asset('storage/' . $this->datasheet_path) : null,
                'filename' => $this->datasheet_filename,
            ] : null,

            'avg_rating'   => $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'review_count' => (int) ($this->reviews_count ?? 0),
            'category'     => new CategoryResource($this->whenLoaded('category')),
            'created_at'   => $this->created_at?->format('d/m/Y'),
        ];
    }
}