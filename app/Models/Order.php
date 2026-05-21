<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total',
        'status',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            // Toujours 2 décimales pour le total
        ];
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
        // Une Order a plusieurs OrderItems
        // On appelle la méthode "items" (pas "orderItems")
        // pour un usage plus naturel : $order->items
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
