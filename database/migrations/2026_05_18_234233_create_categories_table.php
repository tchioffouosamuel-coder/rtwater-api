<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // Nom affiché : "Électronique", "Massage", "Vêtements"...

            $table->string('slug')->unique();
            // Version URL de name : "electronique", "massage", "vetements"
            // Utilisé dans les URLs : /api/categories/electronique
            // unique → deux catégories ne peuvent pas avoir le même slug

            $table->enum('type', ['product', 'service']);
            // 'product' → catégorie pour les produits e-commerce
            // 'service' → catégorie pour les prestations de service
            // Pas de default → obligatoire à la création

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
