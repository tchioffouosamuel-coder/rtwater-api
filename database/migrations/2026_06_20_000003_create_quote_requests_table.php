<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('location')->nullable(); // localisation du projet
            $table->string('solution_type')->nullable(); // forage, purification, pompage, etc.
            $table->integer('volume_m3')->nullable(); // volume d'eau journalier souhaité
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'in_progress', 'quoted', 'converted', 'rejected'])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete(); // devis officiel généré
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
