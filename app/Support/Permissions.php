<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Permissions du backoffice, par rôle.
 *
 * Source unique de vérité : le navigateur ne décide plus de ce qu'il peut faire,
 * il reçoit la liste au moment de la connexion. Jusqu'ici la carte vivait dans
 * le React et l'API ne contrôlait qu'une seule chose — « es-tu admin ? » — ce qui
 * rendait les rôles gestionnaire et caissier inutilisables.
 */
class Permissions
{
    public const VIEW_DASHBOARD    = 'view_dashboard';
    public const VIEW_ANALYTICS    = 'view_analytics';
    public const MANAGE_PRODUCTS   = 'manage_products';
    public const MANAGE_CATEGORIES = 'manage_categories';
    public const MANAGE_SERVICES   = 'manage_services';
    public const MANAGE_ORDERS     = 'manage_orders';
    public const MANAGE_CUSTOMERS  = 'manage_customers';
    public const MANAGE_INVENTORY  = 'manage_inventory';
    public const MANAGE_POS        = 'manage_pos';
    public const MANAGE_RECEIPTS   = 'manage_receipts';
    public const MANAGE_REVIEWS    = 'manage_reviews';
    public const MANAGE_PAGES      = 'manage_pages';
    public const MANAGE_BLOG       = 'manage_blog';
    public const MANAGE_DISCOUNTS  = 'manage_discounts';
    public const MANAGE_SHIPPING   = 'manage_shipping';
    public const MANAGE_NEWSLETTER = 'manage_newsletter';
    public const MANAGE_SETTINGS   = 'manage_settings';
    public const MANAGE_USERS      = 'manage_users';

    /**
     * @return array<string, string[]>  rôle → permissions
     */
    public static function map(): array
    {
        $gestionnaire = [
            self::VIEW_DASHBOARD,
            self::VIEW_ANALYTICS,
            self::MANAGE_PRODUCTS,
            self::MANAGE_CATEGORIES,
            self::MANAGE_SERVICES,
            self::MANAGE_ORDERS,
            self::MANAGE_CUSTOMERS,
            self::MANAGE_INVENTORY,
            self::MANAGE_POS,
            self::MANAGE_RECEIPTS,
            self::MANAGE_REVIEWS,
            self::MANAGE_DISCOUNTS,
            self::MANAGE_SHIPPING,
        ];

        return [
            // L'admin a tout, y compris les réglages et les comptes de l'équipe
            Role::ADMIN => self::all(),

            Role::GESTIONNAIRE => $gestionnaire,

            // Secrétaire : relation client et contenu, pas les prix ni la caisse
            Role::SECRETAIRE => [
                self::VIEW_DASHBOARD,
                self::MANAGE_ORDERS,
                self::MANAGE_CUSTOMERS,
                self::MANAGE_REVIEWS,
                self::MANAGE_PAGES,
                self::MANAGE_BLOG,
                self::MANAGE_NEWSLETTER,
            ],

            // Comptable : lecture des chiffres et des pièces, aucune écriture métier
            Role::COMPTABLE => [
                self::VIEW_DASHBOARD,
                self::VIEW_ANALYTICS,
                self::MANAGE_RECEIPTS,
            ],

            // Client : aucune permission de backoffice
            Role::USER => [],
        ];
    }

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::VIEW_DASHBOARD,
            self::VIEW_ANALYTICS,
            self::MANAGE_PRODUCTS,
            self::MANAGE_CATEGORIES,
            self::MANAGE_SERVICES,
            self::MANAGE_ORDERS,
            self::MANAGE_CUSTOMERS,
            self::MANAGE_INVENTORY,
            self::MANAGE_POS,
            self::MANAGE_RECEIPTS,
            self::MANAGE_REVIEWS,
            self::MANAGE_PAGES,
            self::MANAGE_BLOG,
            self::MANAGE_DISCOUNTS,
            self::MANAGE_SHIPPING,
            self::MANAGE_NEWSLETTER,
            self::MANAGE_SETTINGS,
            self::MANAGE_USERS,
        ];
    }

    /**
     * Permissions effectives d'un utilisateur : l'union de celles de ses rôles.
     *
     * @return string[]
     */
    public static function forUser(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $map = self::map();
        $permissions = [];

        foreach ($user->roles as $role) {
            foreach ($map[$role->name] ?? [] as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }

    public static function userCan(?User $user, string $permission): bool
    {
        return in_array($permission, self::forUser($user), true);
    }

    /** Un compte de backoffice est un compte qui a au moins une permission. */
    public static function isStaff(?User $user): bool
    {
        return self::forUser($user) !== [];
    }
}
