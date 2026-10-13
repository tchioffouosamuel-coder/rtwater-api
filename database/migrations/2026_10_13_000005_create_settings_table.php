<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de la boutique, en clé/valeur. La valeur est stockée en JSON pour
 * accepter indifféremment un texte, un nombre, un booléen ou une liste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->string('group', 50)->default('general'); // general, payment, stock, notifications, display
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
