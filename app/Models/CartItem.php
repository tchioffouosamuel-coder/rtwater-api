<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
    ];

    // ═══════════════════════════════
    // RELATIONS
    // ═══════════════════════════════

    public function user()
    {
        return $this->belongsTo(User::class);
        // Un CartItem APPARTIENT À un User
        // Utilisation : $cartItem->user
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
        // Un CartItem APPARTIENT À un Product
        // Utilisation : $cartItem->product
        //               $cartItem->product->name  → "iPhone 15"
        //               $cartItem->product->price → 999.99
    }

    // ═══════════════════════════════
    // MÉTHODES UTILITAIRES
    // ═══════════════════════════════

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->product->price;
        // Un "Accessor" = une propriété calculée automatiquement
        // Nom de la méthode : get + NomEnPascalCase + Attribute
        // → getSubtotalAttribute → accessible via $cartItem->subtotal
        //
        // Utilisation :
        // $cartItem->subtotal → retourne quantity × price
        // ex: 3 × 999.99 = 2999.97
        //
        // C'est une propriété virtuelle — elle n'existe pas en DB
        // Elle est calculée à la volée quand tu y accèdes
    }
}
