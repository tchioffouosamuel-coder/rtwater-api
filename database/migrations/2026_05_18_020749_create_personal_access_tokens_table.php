<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            $table->morphs('tokenable');
            // Crée deux colonnes :
            // tokenable_type VARCHAR(255) → le type du modèle (ex: "App\Models\User")
            // tokenable_id   BIGINT       → l'id du modèle (ex: 5)
            // Permet à Sanctum d'associer un token à n'importe quel modèle

            $table->string('name');
            // Nom du token : "mobile-app", "web-browser"...

            $table->string('token', 64)->unique();
            // Le token lui-même (hashé, 64 caractères)
            // unique → chaque token est différent

            $table->text('abilities')->nullable();
            // Les permissions de ce token en JSON
            // ex: ["read", "write"] ou ["*"] pour tout accéder

            $table->timestamp('last_used_at')->nullable();
            // Dernière utilisation du token
            // Utile pour détecter les tokens inactifs

            $table->timestamp('expires_at')->nullable();
            // Date d'expiration du token
            // NULL = le token n'expire jamais

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
