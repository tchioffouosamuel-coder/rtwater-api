<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // admin qui a saisi
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // commande source si décrémentation auto
            $table->enum('type', ['in', 'out', 'adjustment']); // entrée stock, sortie commande, correction manuelle
            $table->integer('quantity'); // positif = entrée, négatif = sortie
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('reason')->nullable(); // 'order', 'restock', 'loss', 'correction', etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
