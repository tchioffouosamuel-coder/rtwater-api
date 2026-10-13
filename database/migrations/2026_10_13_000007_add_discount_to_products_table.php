<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remise par produit, en pourcentage. C'est ce champ qui alimente les badges
 * « −20 % » et la section « Offres du moment » de la boutique : jusqu'ici le
 * front les affichait à partir d'un champ que l'API n'envoyait jamais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount')->default(0)->after('cost'); // 0 à 100 %
            $table->timestamp('discount_ends_at')->nullable()->after('discount'); // null = sans fin
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount', 'discount_ends_at']);
        });
    }
};
