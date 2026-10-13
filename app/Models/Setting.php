<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Réglages de la boutique, en clé/valeur.
 *
 * Lus à chaque requête (devise, TVA, seuils de stock) : tout le jeu est donc
 * mis en cache d'un bloc et le cache est vidé à la première écriture.
 */
class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'group'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    private const CACHE_KEY = 'settings.all';

    /**
     * Valeurs par défaut — font aussi office de schéma : une clé absente d'ici
     * est refusée à l'écriture, ce qui évite qu'un réglage fantôme s'installe.
     *
     * @return array<string, array{group:string, default:mixed}>
     */
    public static function schema(): array
    {
        return [
            // Général — identité de l'entreprise
            'company_name'        => ['group' => 'general', 'default' => 'RT Water Solution'],
            'company_email'       => ['group' => 'general', 'default' => 'contact@rtwater.cm'],
            'company_phone'       => ['group' => 'general', 'default' => '+237 6 90 00 00 00'],
            'company_phone_2'     => ['group' => 'general', 'default' => ''],
            'company_address'     => ['group' => 'general', 'default' => 'Yaoundé, Cameroun'],
            'company_tax_id'      => ['group' => 'general', 'default' => ''], // NIU
            'site_description'    => ['group' => 'general', 'default' => "Traitement et distribution d'eau potable au Cameroun"],

            // Paiement
            'currency'            => ['group' => 'payment', 'default' => 'XAF'],
            'tax_rate'            => ['group' => 'payment', 'default' => 0],      // TVA en %, 0 = non assujetti
            'tax_included'        => ['group' => 'payment', 'default' => true],   // prix affichés TTC
            'payment_mobile_money'=> ['group' => 'payment', 'default' => true],
            'payment_cash'        => ['group' => 'payment', 'default' => true],

            // Stock
            'low_stock_alert'     => ['group' => 'stock', 'default' => 10],
            'critical_stock_level'=> ['group' => 'stock', 'default' => 3],

            // Notifications
            'notify_orders'       => ['group' => 'notifications', 'default' => true],
            'notify_stock'        => ['group' => 'notifications', 'default' => true],
            'notification_email'  => ['group' => 'notifications', 'default' => ''],

            // Affichage
            'products_per_page'   => ['group' => 'display', 'default' => 12],
            'default_language'    => ['group' => 'display', 'default' => 'fr'],
        ];
    }

    /** @return array<string, mixed> Réglages effectifs : valeurs en base par-dessus les défauts. */
    public static function values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $defaults = array_map(fn($meta) => $meta['default'], self::schema());

            $stored = static::query()->pluck('value', 'key')->toArray();

            // Une clé retirée du schéma reste en base mais n'est plus servie
            return array_merge($defaults, array_intersect_key($stored, $defaults));
        });
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        return self::values()[$key] ?? $fallback;
    }

    /**
     * Écrit les réglages fournis. Les clés hors schéma sont ignorées.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>  les réglages effectifs après écriture
     */
    public static function put(array $values): array
    {
        $schema = self::schema();

        foreach ($values as $key => $value) {
            if (!isset($schema[$key])) {
                continue;
            }

            static::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $schema[$key]['group']]
            );
        }

        Cache::forget(self::CACHE_KEY);

        return self::values();
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
