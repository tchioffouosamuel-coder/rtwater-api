<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réglages de la boutique.
 *
 * `public()` n'expose que ce dont le site vitrine a besoin (coordonnées, devise) ;
 * les seuils de stock et les e-mails de notification restent côté admin.
 */
class SettingController extends Controller
{
    /** Clés servies au site public. */
    private const PUBLIC_KEYS = [
        'company_name', 'company_email', 'company_phone', 'company_phone_2',
        'company_address', 'company_tax_id', 'site_description',
        'currency', 'tax_rate', 'tax_included',
        'payment_mobile_money', 'payment_cash',
        'products_per_page', 'default_language',
    ];

    /** GET /api/settings — public. */
    public function publicIndex(): JsonResponse
    {
        return response()->json([
            'data' => array_intersect_key(Setting::values(), array_flip(self::PUBLIC_KEYS)),
        ]);
    }

    /** GET /api/admin/settings — tous les réglages, regroupés par onglet. */
    public function index(): JsonResponse
    {
        $values = Setting::values();
        $groups = [];

        foreach (Setting::schema() as $key => $meta) {
            $groups[$meta['group']][$key] = $values[$key] ?? $meta['default'];
        }

        return response()->json([
            'data'   => $values,
            'groups' => $groups,
        ]);
    }

    /** PUT /api/admin/settings */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name'         => 'sometimes|string|max:150',
            'company_email'        => 'sometimes|nullable|email|max:150',
            'company_phone'        => 'sometimes|nullable|string|max:40',
            'company_phone_2'      => 'sometimes|nullable|string|max:40',
            'company_address'      => 'sometimes|nullable|string|max:255',
            'company_tax_id'       => 'sometimes|nullable|string|max:60',
            'site_description'     => 'sometimes|nullable|string|max:500',

            'currency'             => 'sometimes|string|size:3',
            'tax_rate'             => 'sometimes|numeric|min:0|max:100',
            'tax_included'         => 'sometimes|boolean',
            'payment_mobile_money' => 'sometimes|boolean',
            'payment_cash'         => 'sometimes|boolean',

            'low_stock_alert'      => 'sometimes|integer|min:0|max:100000',
            'critical_stock_level' => 'sometimes|integer|min:0|max:100000',

            'notify_orders'        => 'sometimes|boolean',
            'notify_stock'         => 'sometimes|boolean',
            'notification_email'   => 'sometimes|nullable|email|max:150',

            'products_per_page'    => 'sometimes|integer|min:4|max:48',
            'default_language'     => 'sometimes|in:fr,en',
        ], [
            'currency.size'           => 'Le code devise doit faire 3 lettres (XAF, EUR…).',
            'products_per_page.max'   => 'Au-delà de 48 produits par page, la boutique devient lente.',
        ]);

        if (isset($validated['critical_stock_level'], $validated['low_stock_alert'])
            && $validated['critical_stock_level'] > $validated['low_stock_alert']) {
            return response()->json([
                'message' => "Le seuil critique doit être inférieur ou égal au seuil d'alerte.",
                'errors'  => ['critical_stock_level' => ["Le seuil critique doit rester sous le seuil d'alerte."]],
            ], 422);
        }

        return response()->json([
            'message' => 'Réglages enregistrés',
            'data'    => Setting::put($validated),
        ]);
    }
}
