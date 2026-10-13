<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zones de livraison et leur tarif. Le client choisit sa zone au moment de
 * commander ; le tarif s'ajoute au sous-panier, sauf franco de port.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // « Yaoundé — centre »
            $table->string('description')->nullable();               // quartiers couverts
            $table->decimal('fee', 10, 2)->default(0);               // frais de livraison
            $table->decimal('free_above', 10, 2)->nullable();        // franco de port à partir de ce montant
            $table->string('delay')->nullable();                     // « 24 à 48 h »
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
