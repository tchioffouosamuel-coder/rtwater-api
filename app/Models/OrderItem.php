<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    // ═══════════════════════════════
    // RELATIONS
    // ═══════════════════════════════

    public function order()
    {
        return $this->belongsTo(Order::class);
        // Un OrderItem APPARTIENT À une Order
        // Utilisation : $orderItem->order
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
        // Un OrderItem APPARTIENT À un Product
        // Utilisation : $orderItem->product->name
    }

    // ═══════════════════════════════
    // MÉTHODES UTILITAIRES
    // ═══════════════════════════════

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->price;
        // Sous-total de cet article :
        // quantity × price (prix au moment de l'achat)
        // Utilisation : $orderItem->subtotal
    }
}
