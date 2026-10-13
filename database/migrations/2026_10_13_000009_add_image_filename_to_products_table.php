<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nom de fichier image attendu par un produit.
 *
 * Permet de dissocier les deux temps de l'import : le fichier Excel declare
 * « RTW-B15.jpg » dans sa colonne image, le produit est cree sans visuel, puis
 * les photos sont televersees en masse plus tard et rattachees par ce nom.
 *
 * Remis a null des que l'image est effectivement en place : la colonne ne
 * contient donc que les produits encore en attente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_filename')->nullable()->after('image_url');
            $table->index('image_filename');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['image_filename']);
            $table->dropColumn('image_filename');
        });
    }
};
