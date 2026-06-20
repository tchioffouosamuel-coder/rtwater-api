<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('sebpay_transaction_id')->nullable()->unique();
            $table->string('operator')->nullable(); // orange_money, mtn_money, moov_money
            $table->string('phone')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('XOF');
            $table->enum('status', ['pending', 'confirmed', 'failed', 'expired'])->default('pending');
            $table->json('sebpay_response')->nullable(); // réponse brute SEBPAY pour debug
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('sebpay_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
