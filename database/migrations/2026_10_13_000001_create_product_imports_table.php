<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // admin qui a lancé l'import
            $table->string('filename');                 // nom du fichier Excel/CSV d'origine
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('images_count')->default(0); // images rattachées automatiquement
            $table->json('options')->nullable();        // choix faits dans l'assistant
            $table->json('errors')->nullable();         // lignes rejetées : [{line, messages[]}]
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
