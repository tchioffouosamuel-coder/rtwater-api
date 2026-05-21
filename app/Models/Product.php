<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            // Garantit que price est toujours retourné avec 2 décimales
            // 999 → "999.00"  /  999.9 → "999.90"

            'is_active' => 'boolean',
            // MySQL stocke 0 ou 1 dans la colonne TINYINT
            // 'boolean' → Laravel convertit automatiquement
            // 0 → false  /  1 → true
            // Utilisation : if ($product->is_active) { ... }
        ];
    }

    // ═══════════════════════════════
    // RELATIONS
    // ═══════════════════════════════

    public function category()
    {
        return $this->belongsTo(Category::class);
        // Un Product APPARTIENT À une Category
        // Laravel cherche category_id dans la table products
        // Utilisation : $product->category
        //               $product->category->name → "Électronique"
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
        // Un Product peut être dans plusieurs paniers
        // Utilisation : $product->cartItems
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
        // Un Product peut être dans plusieurs order_items
        // Utilisation : $product->orderItems
    }

    // ═══════════════════════════════
    // SCOPES — filtres réutilisables
    // ═══════════════════════════════

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
        // Un "scope" c'est un filtre réutilisable qu'on peut chaîner
        // Sans scope :
        // Product::where('is_active', true)->get()
        //
        // Avec scope :
        // Product::active()->get()
        //
        // On peut aussi chaîner :
        // Product::active()->where('price', '<', 100)->get()
    }

    public function scopeInStock(Builder $query)
    {
        return $query->where('stock', '>', 0);
        // Filtre les produits qui ont du stock
        // Utilisation : Product::inStock()->get()
        // Combiné    : Product::active()->inStock()->get()
    }
}
