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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            // Clé étrangère vers users.id
            // Si un utilisateur est supprimé, tous ses items de panier sont aussi supprimés (cascade)
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            // Clé étrangère vers products.id
            $table->integer('quantity')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
            // Un même produit ne peut apparaître qu'une seule fois dans le panier d'un utilisateur (la quantité peut être mise à jour, mais pas dupliquer l'item)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
