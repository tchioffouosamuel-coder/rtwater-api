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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');
            // Clé étrangère vers orders.id
            // Si une commande est supprimée, tous ses items de commande sont aussi supprimés (cascade)
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            // Clé étrangère vers products.id
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            // Prix unitaire au moment de la commande (pour garder l'historique même si le prix du produit change ensuite)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
