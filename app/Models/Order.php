<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'subtotal',
        'shipping_fee',
        'discount_total',
        'discount_code',
        'shipping_zone_id',
        'total',
        'status',
        'payment_method',
        'address',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'       => 'decimal:2',
            'shipping_fee'   => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total'          => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Toute commande part avec une référence, y compris celles créées en test
        static::creating(function (self $order) {
            $order->reference ??= self::nextReference();
        });
    }

    // ═══════════════════════════════
    // RÉFÉRENCE
    // ═══════════════════════════════

    /**
     * Référence du type CMD-261013-K7M2Q.
     *
     * La partie aléatoire est délibérée : une référence dérivée de l'id rendait
     * le nombre de commandes public et les références énumérables.
     */
    public static function nextReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I, O, 0, 1 : illisibles au téléphone

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 5; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $reference = 'CMD-' . now()->format('ymd') . '-' . $suffix;

            if (!static::where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        // Collision 10 fois de suite : on retombe sur une valeur forcément unique
        return 'CMD-' . now()->format('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /** Retrouve une commande à partir d'une référence saisie par le client. */
    public static function findByReference(?string $reference): ?self
    {
        $reference = strtoupper(trim((string) $reference));
        if ($reference === '') {
            return null;
        }

        $order = static::where('reference', $reference)->first();
        if ($order) {
            return $order;
        }

        // Références historiques, dérivées de l'id (CMD-000042, #42, 42)
        $digits = preg_replace('/\D/', '', $reference);

        return $digits === '' ? null : static::find((int) $digits);
    }

    // ═══════════════════════════════
    // RELATIONS
    // ═══════════════════════════════

    public function user()
    {
        return $this->belongsTo(User::class);
        // Une Order APPARTIENT À un User
        // Utilisation : $order->user->name
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function shippingZone()
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ═══════════════════════════════
    // SCOPES
    // ═══════════════════════════════

    public function scopeByStatus(\Illuminate\Database\Eloquent\Builder $query, string $status)
    {
        return $query->where('status', $status);
        // Scope avec paramètre
        // Utilisation : Order::byStatus('paid')->get()
        //               Order::byStatus('pending')->get()
    }
}
