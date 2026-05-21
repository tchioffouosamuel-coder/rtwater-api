<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price'        => 'decimal:2',
            'is_available' => 'boolean',
            // Même logique que Product
        ];
    }

    // ═══════════════════════════════
    // RELATIONS
    // ═══════════════════════════════

    public function category()
    {
        return $this->belongsTo(Category::class);
        // Un Service APPARTIENT À une Category
        // Utilisation : $service->category->name
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
        // Un Service peut avoir plusieurs Bookings
        // Utilisation : $service->bookings
        //               $service->bookings->count() → nombre de RDV
    }

    // ═══════════════════════════════
    // SCOPES
    // ═══════════════════════════════

    public function scopeAvailable(Builder $query)
    {
        return $query->where('is_available', true);
        // Filtre les services disponibles
        // Utilisation : Service::available()->get()
    }
}
