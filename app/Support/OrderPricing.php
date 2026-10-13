<?php

namespace App\Support;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;

/**
 * Calcule le montant d'une commande : sous-total remisé, livraison, code promo.
 *
 * Centralisé ici parce que trois chemins créent des commandes (boutique,
 * invité, caisse) et qu'ils doivent tous appliquer les mêmes règles — et
 * surtout jamais faire confiance aux montants envoyés par le navigateur.
 */
class OrderPricing
{
    /**
     * Prix de vente effectif d'un produit, remise produit appliquée.
     *
     * La remise n'est prise en compte que si elle n'est pas expirée : un produit
     * à −20 % jusqu'à hier se revend à plein tarif aujourd'hui, sans intervention.
     */
    public static function unitPrice(Product $product): float
    {
        $percent = (int) ($product->discount ?? 0);

        if ($percent <= 0 || $percent > 100) {
            return (float) $product->price;
        }
        if ($product->discount_ends_at && $product->discount_ends_at->isPast()) {
            return (float) $product->price;
        }

        return round((float) $product->price * (100 - $percent) / 100, 2);
    }

    /**
     * Décompose une commande.
     *
     * @param  iterable<array{product: Product, quantity: int}>  $lines
     * @return array{
     *     subtotal: float, shipping_fee: float, discount_total: float,
     *     total: float, discount_code: ?string, discount_error: ?string,
     *     shipping_zone: ?ShippingZone
     * }
     */
    public static function quote(
        iterable $lines,
        ?ShippingZone $zone = null,
        ?string $code = null,
        ?User $user = null,
    ): array {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += self::unitPrice($line['product']) * (int) $line['quantity'];
        }
        $subtotal = round($subtotal, 2);

        $discountTotal = 0.0;
        $discountCode  = null;
        $discountError = null;

        if ($code !== null && trim($code) !== '') {
            $discount = Discount::findByCode($code);

            if (!$discount) {
                $discountError = "Ce code promo n'existe pas.";
            } elseif ($reason = $discount->check($subtotal, $user)) {
                $discountError = $reason;
            } else {
                $discountTotal = $discount->amountFor($subtotal);
                $discountCode  = $discount->code;
            }
        }

        // La livraison se calcule sur le sous-total avant remise : un code promo
        // ne doit pas faire perdre le franco de port déjà atteint.
        $shippingFee = $zone ? $zone->feeFor($subtotal) : 0.0;

        return [
            'subtotal'       => $subtotal,
            'shipping_fee'   => round($shippingFee, 2),
            'discount_total' => $discountTotal,
            'total'          => round(max(0, $subtotal - $discountTotal + $shippingFee), 2),
            'discount_code'  => $discountCode,
            'discount_error' => $discountError,
            'shipping_zone'  => $zone,
        ];
    }
}
