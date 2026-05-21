<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'scheduled_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            // Convertit la string "2024-03-15 14:30:00"
            // en objet Carbon avec plein de méthodes utiles :
            // $booking->scheduled_at->format('d/m/Y à H:i')
            // → "15/03/2024 à 14:30"
            // $booking->scheduled_at->isPast()
            // → true si la date est passée
            // $booking->scheduled_at->isFuture()
            // → true si la date est dans le futur
        ];
    }

    // ═══════════════════════════════
    // RELATIONS
    // ═══════════════════════════════

    public function user()
    {
        return $this->belongsTo(User::class);
        // Un Booking APPARTIENT À un User
        // Utilisation : $booking->user->name
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
        // Un Booking APPARTIENT À un Service
        // Utilisation : $booking->service->name
        //               $booking->service->duration_minutes
    }

    // ═══════════════════════════════
    // SCOPES
    // ═══════════════════════════════

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now());
        // Filtre les RDV dans le futur
        // now() → fonction Laravel qui retourne la date/heure actuelle
        // Utilisation : Booking::upcoming()->get()
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
        // Filtre par statut
        // Utilisation : Booking::byStatus('confirmed')->get()
    }

}
