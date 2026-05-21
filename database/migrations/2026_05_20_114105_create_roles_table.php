<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            // Nom technique du rôle : 'admin', 'gestionnaire'...
            // unique() → deux rôles ne peuvent pas avoir le même nom

            $table->string('label');
            // Nom lisible affiché à l'écran : 'Administrateur', 'Gestionnaire'...
            // Pas unique car deux rôles pourraient avoir des labels similaires

            $table->text('description')->nullable();
            // Description du rôle : "Peut gérer les produits et les commandes"

            $table->boolean('is_default')->default(false);
            // true  → ce rôle est attribué automatiquement à tout nouvel utilisateur
            // false → rôle normal
            // Le rôle 'user' sera is_default = true

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
