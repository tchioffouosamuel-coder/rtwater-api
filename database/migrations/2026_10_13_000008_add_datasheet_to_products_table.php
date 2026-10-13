<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche technique du produit, sous l'une de trois formes : texte saisi dans le
 * backoffice, image (tableau de caractéristiques scanné) ou document PDF du
 * fabricant.
 *
 * `datasheet_type` dit laquelle est en place, ce qui évite d'avoir à deviner
 * d'après les colonnes renseignées et permet au front d'afficher une seule chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('datasheet_type', ['text', 'image', 'pdf'])->nullable()->after('description');
            $table->longText('datasheet_text')->nullable()->after('datasheet_type');
            $table->string('datasheet_path')->nullable()->after('datasheet_text');
            // Nom d'origine : sert de libellé au lien de téléchargement, le chemin
            // stocké étant une chaîne aléatoire.
            $table->string('datasheet_filename')->nullable()->after('datasheet_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['datasheet_type', 'datasheet_text', 'datasheet_path', 'datasheet_filename']);
        });
    }
};
