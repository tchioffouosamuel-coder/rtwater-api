<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes promo. `used_count` est incrémenté à la commande : c'est lui qui fait
 * respecter `max_uses`, donc il est mis à jour sous verrou dans la transaction
 * de commande, jamais recalculé à la volée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();                 // « BIENVENUE10 », saisi par le client
            $table->string('label')->nullable();                  // libellé interne
            $table->enum('type', ['percent', 'fixed']);           // −10 % ou −1000 XAF
            $table->decimal('value', 10, 2);
            $table->decimal('min_subtotal', 10, 2)->nullable();   // panier minimum
            $table->unsignedInteger('max_uses')->nullable();      // null = illimité
            $table->unsignedInteger('max_uses_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
