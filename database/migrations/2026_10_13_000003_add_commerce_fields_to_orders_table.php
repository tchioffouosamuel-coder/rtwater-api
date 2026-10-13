<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Décompose le total d'une commande (sous-total, livraison, remise) et lui donne
 * une référence propre, stockée en base au lieu d'être dérivée de l'id.
 *
 * Dériver la référence de l'id rendait le volume d'affaires public (CMD-000041
 * annonce 40 commandes) et les références énumérables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->after('id');
            $table->decimal('subtotal', 10, 2)->default(0)->after('reference');
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('subtotal');
            $table->decimal('discount_total', 10, 2)->default(0)->after('shipping_fee');
            $table->string('discount_code', 50)->nullable()->after('discount_total');
            $table->foreignId('shipping_zone_id')->nullable()->after('discount_code')
                ->constrained()->nullOnDelete();
            $table->string('payment_method', 30)->nullable()->after('status'); // mobile_money, cash
            $table->text('admin_notes')->nullable()->after('address');         // commentaire interne
        });

        // Les commandes déjà passées gardent la référence que le client a reçue
        DB::table('orders')->whereNull('reference')->orderBy('id')->chunkById(500, function ($orders) {
            foreach ($orders as $order) {
                DB::table('orders')->where('id', $order->id)->update([
                    'reference' => 'CMD-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                    'subtotal'  => $order->total,
                ]);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('reference', 32)->nullable(false)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_zone_id');
            $table->dropUnique(['reference']);
            $table->dropColumn([
                'reference', 'subtotal', 'shipping_fee', 'discount_total',
                'discount_code', 'payment_method', 'admin_notes',
            ]);
        });
    }
};
