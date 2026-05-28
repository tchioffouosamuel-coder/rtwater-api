<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            // ✅ user_id (pas 'users')
            // Laravel cherche automatiquement la table 'users'
            // grâce au nom 'user_id'

            $table->decimal('total', 10, 2);

            $table->enum('status', [
                'pending',
                'paid',
                'shipped',
                'delivered',
                'cancelled'
            ])->default('pending');

            $table->text('address');
            // ✅ address (pas 'adresse')
            // text() au lieu de string() car adresse peut être longue

            $table->timestamps();
            // ✅ toujours à la fin
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
