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
        'sku',
        'description',
        'price',
        'cost',
        'discount',
        'discount_ends_at',
        'stock',
        'min_quantity',
        'max_quantity',
        'location',
        'image_url',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'discount'         => 'integer',
            'discount_ends_at' => 'datetime',
            // Garantit que price est toujours retourné avec 2 décimales
            // 999 → "999.00"  /  999.9 → "999.90"

            'is_available' => 'boolean',
            // MySQL stocke 0 ou 1 dans la colonne TINYINT
            // 'boolean' → Laravel convertit automatiquement
            // 0 → false  /  1 → true
            // Utilisation : if ($product->is_available) { ... }
        ];
    }

    protected static function booted(): void
    {
        // Le sitemap liste les produits : on le régénère dès qu'un produit change
        $forget = function () {
            \Illuminate\Support\Facades\Cache::forget('sitemap.xml');
            \App\Support\CacheVersion::bump('categories');
        };
        static::saved($forget);
        static::deleted($forget);
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
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // ═══════════════════════════════
    // SCOPES — filtres réutilisables
    // ═══════════════════════════════

    /** Remise en cours, 0 si absente ou expiree. */
    public function activeDiscount(): int
    {
        $percent = (int) ($this->discount ?? 0);

        if ($percent <= 0 || $percent > 100) {
            return 0;
        }
        if ($this->discount_ends_at && $this->discount_ends_at->isPast()) {
            return 0;
        }

        return $percent;
    }

    /** Produits reellement en promotion a cet instant. */
    public function scopeOnSale(Builder $query)
    {
        return $query->where('discount', '>', 0)
            ->where(function (Builder $q) {
                $q->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>', now());
            });
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_available', true);
        // Un "scope" c'est un filtre réutilisable qu'on peut chaîner
        // Sans scope :
        // Product::where('is_available', true)->get()
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
