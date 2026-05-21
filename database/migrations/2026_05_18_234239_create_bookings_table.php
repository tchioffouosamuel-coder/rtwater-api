<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            // Qui a fait la réservation ?

            $table->foreignId('service_id')
                ->constrained()
                ->onDelete('cascade');
            // Quel service est réservé ?

            $table->dateTime('scheduled_at');
            // Date ET heure du rendez-vous
            // Format : "2024-03-15 14:30:00"
            // dateTime = DATETIME en SQL (date + heure)
            // Contrairement à date() qui stocke seulement la date

            $table->enum('status', [
                'pending',    // Demande envoyée, pas encore confirmée
                'confirmed',  // Confirmée par le prestataire
                'cancelled',  // Annulée (par le client ou le prestataire)
                'completed'   // Prestation effectuée
            ])->default('pending');

            $table->text('notes')->nullable();
            // Notes du client pour le prestataire
            // "Allergie aux huiles", "Première séance"...
            // nullable → optionnel

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
