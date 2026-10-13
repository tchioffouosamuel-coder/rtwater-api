<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Zone de livraison et son tarif. */
class ShippingZone extends Model
{
    protected $fillable = [
        'name',
        'description',
        'fee',
        'free_above',
        'delay',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fee'        => 'decimal:2',
            'free_above' => 'decimal:2',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Frais réellement dus pour un sous-panier donné : 0 au-delà du franco de port. */
    public function feeFor(float $subtotal): float
    {
        if ($this->free_above !== null && $subtotal >= (float) $this->free_above) {
            return 0.0;
        }

        return (float) $this->fee;
    }
}
