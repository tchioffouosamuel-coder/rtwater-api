<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            // ⚠️ Pas de $table->id() ici
            // Une table pivot n'a pas besoin de clé primaire propre
            // La combinaison (user_id + role_id) est déjà unique

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            // Si l'user est supprimé → ses attributions de rôles aussi

            $table->foreignId('role_id')
                ->constrained()
                ->onDelete('cascade');
            // Si le rôle est supprimé → les attributions sont supprimées aussi

            $table->primary(['user_id', 'role_id']);
            // Clé primaire composite :
            // La paire (user_id + role_id) est unique
            // Un user ne peut pas avoir le même rôle deux fois

            $table->timestamps();
            // created_at = quand ce rôle a été attribué à cet user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
