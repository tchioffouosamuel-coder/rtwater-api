<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('users')
            ->constrained()
            ->onDelete('cascade');
            $table->decimal('total', 10, 2);
            $table->enum('status', [
                'pending',    // En attente de paiement
                'paid',       // Paiement reçu
                'shipped',    // Colis envoyé
                'delivered',  // Colis reçu par le client
                'cancelled'   // Commande annulée
            ])->default('pending');
            // Cycle de vie d'une commande
            // pending → paid → shipped → delivered
            //        ↘ cancelled (à tout moment)

            $table->timestamps();
            $table->string('adresse');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
