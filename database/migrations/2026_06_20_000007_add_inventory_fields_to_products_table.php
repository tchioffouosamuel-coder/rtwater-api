<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('name');
            $table->decimal('cost', 10, 2)->nullable()->after('price'); // prix d'achat (distinct du prix de vente)
            $table->integer('min_quantity')->default(0)->after('stock');
            $table->integer('max_quantity')->default(0)->after('min_quantity');
            $table->string('location')->nullable()->after('max_quantity'); // localisation entrepôt
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'cost', 'min_quantity', 'max_quantity', 'location']);
        });
    }
};
