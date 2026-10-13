<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fondations commerciales : reference de commande, verrou de stock, frais de
 * livraison, codes promo, reglages, permissions par role, newsletter.
 */
class CommerceFoundationsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $labels = [
            Role::ADMIN        => 'Administrateur',
            Role::GESTIONNAIRE => 'Gestionnaire',
            Role::SECRETAIRE   => 'Secrétaire',
            Role::COMPTABLE    => 'Comptable',
            Role::USER         => 'Client',
        ];

        Role::firstOrCreate(['name' => $role], ['label' => $labels[$role] ?? $role]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function guestOrderPayload(Product $product, array $extra = []): array
    {
        return array_merge([
            'name'    => 'Awa Client',
            'phone'   => '+237 690 11 22 33',
            'address' => 'Bastos, Yaoundé',
            'items'   => [['product_id' => $product->id, 'quantity' => 1]],
        ], $extra);
    }

    // ═══════════════════════════════════════════════════
    // 1 — RÉFÉRENCE DE COMMANDE
    // ═══════════════════════════════════════════════════

    public function test_order_reference_does_not_leak_the_order_count(): void
    {
        $product = Product::factory()->create(['stock' => 50, 'price' => 1000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product))->assertCreated();
        $order = Order::latest('id')->first();

        // Ni l'id, ni un compteur sequentiel ne doivent apparaitre
        $this->assertMatchesRegularExpression('/^CMD-\d{6}-[A-Z2-9]{5}$/', $order->reference);
        $this->assertStringNotContainsString((string) $order->id, substr($order->reference, 10));
    }

    public function test_references_are_unique_across_many_orders(): void
    {
        $references = collect(range(1, 40))->map(fn() => Order::nextReference());

        $this->assertCount(40, $references->unique());
    }

    public function test_tracking_works_with_the_stored_reference(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 2000]);
        $this->postJson('/api/orders', $this->guestOrderPayload($product))->assertCreated();
        $order = Order::latest('id')->first();

        $this->getJson("/api/orders/track?reference={$order->reference}&contact=690112233")
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference);
    }

    public function test_tracking_still_accepts_legacy_numeric_references(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 2000]);
        $this->postJson('/api/orders', $this->guestOrderPayload($product))->assertCreated();
        $order = Order::latest('id')->first();

        // Une commande d'avant la migration : le client a recu CMD-000001
        $legacy = 'CMD-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

        $this->getJson("/api/orders/track?reference={$legacy}&contact=690112233")
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference);
    }

    // ═══════════════════════════════════════════════════
    // 1 — VERROU DE STOCK
    // ═══════════════════════════════════════════════════

    public function test_order_is_refused_when_stock_runs_out(): void
    {
        $product = Product::factory()->create(['stock' => 1, 'price' => 5000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product))->assertCreated();

        $this->postJson('/api/orders', $this->guestOrderPayload($product))
            ->assertStatus(422)
            ->assertJsonPath('message', fn($m) => str_contains($m, 'Stock insuffisant'));

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_stock_never_goes_negative_even_with_the_same_product_twice_in_one_order(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'price' => 1000]);

        // Deux lignes sur le meme produit : 2 + 2 = 4 > 3
        $this->postJson('/api/orders', $this->guestOrderPayload($product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]))->assertStatus(422);

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_failed_order_leaves_no_trace(): void
    {
        $ok   = Product::factory()->create(['stock' => 10, 'price' => 1000]);
        $late = Product::factory()->create(['stock' => 0, 'price' => 1000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($ok, [
            'items' => [
                ['product_id' => $ok->id, 'quantity' => 1],
                ['product_id' => $late->id, 'quantity' => 1],
            ],
        ]))->assertStatus(422);

        // La transaction a ete annulee : aucun stock entame, aucune commande
        $this->assertSame(10, $ok->fresh()->stock);
        $this->assertSame(0, Order::count());
    }

    // ═══════════════════════════════════════════════════
    // 2 — REMISE PRODUIT RÉELLE
    // ═══════════════════════════════════════════════════

    public function test_product_discount_is_exposed_and_applied_to_the_order(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 10000, 'discount' => 20]);

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->assertSame(20, $response->json('data.discount'));
        $this->assertEquals(8000, $response->json('data.final_price'));

        $this->postJson('/api/orders', $this->guestOrderPayload($product))->assertCreated();

        // Le prix remise est celui facture, pas le prix barre
        $this->assertEquals(8000, Order::latest('id')->first()->total);
    }

    public function test_expired_discount_is_ignored(): void
    {
        $product = Product::factory()->create([
            'stock' => 10, 'price' => 10000,
            'discount' => 30, 'discount_ends_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->assertSame(0, $response->json('data.discount'));
        $this->assertEquals(10000, $response->json('data.final_price'));
    }

    public function test_on_sale_filter_lists_only_running_promotions(): void
    {
        Product::factory()->create(['name' => 'En promo', 'discount' => 15, 'is_available' => true]);
        Product::factory()->create(['name' => 'Promo finie', 'discount' => 15, 'discount_ends_at' => now()->subHour(), 'is_available' => true]);
        Product::factory()->create(['name' => 'Plein tarif', 'discount' => 0, 'is_available' => true]);

        $names = collect($this->getJson('/api/products?on_sale=1')->assertOk()->json('data'))
            ->pluck('name');

        $this->assertSame(['En promo'], $names->all());
    }

    // ═══════════════════════════════════════════════════
    // 8 — LIVRAISON
    // ═══════════════════════════════════════════════════

    public function test_shipping_fee_is_added_to_the_order_total(): void
    {
        $zone    = ShippingZone::create(['name' => 'Centre', 'fee' => 1500]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 4000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, [
            'shipping_zone_id' => $zone->id,
        ]))->assertCreated();

        $order = Order::latest('id')->first();
        $this->assertEquals(4000, $order->subtotal);
        $this->assertEquals(1500, $order->shipping_fee);
        $this->assertEquals(5500, $order->total);
        $this->assertSame($zone->id, $order->shipping_zone_id);
    }

    public function test_free_shipping_above_threshold(): void
    {
        $zone    = ShippingZone::create(['name' => 'Centre', 'fee' => 1500, 'free_above' => 25000]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 30000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, [
            'shipping_zone_id' => $zone->id,
        ]))->assertCreated();

        $order = Order::latest('id')->first();
        $this->assertEquals(0, $order->shipping_fee);
        $this->assertEquals(30000, $order->total);
    }

    public function test_public_shipping_zones_report_the_fee_for_the_current_cart(): void
    {
        ShippingZone::create(['name' => 'Centre', 'fee' => 1500, 'free_above' => 25000]);
        ShippingZone::create(['name' => 'Inactive', 'fee' => 9999, 'is_active' => false]);

        $zones = $this->getJson('/api/shipping-zones?subtotal=30000')->assertOk()->json('data');

        $this->assertCount(1, $zones);
        $this->assertEquals(1500, $zones[0]['fee']);
        $this->assertEquals(0, $zones[0]['fee_for_cart']);
    }

    // ═══════════════════════════════════════════════════
    // 8 — CODES PROMO
    // ═══════════════════════════════════════════════════

    public function test_percent_discount_code_applies_to_the_order(): void
    {
        Discount::create(['code' => 'bienvenue10', 'type' => 'percent', 'value' => 10]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 20000]);

        // Le code est saisi en minuscules : il doit etre reconnu
        $this->postJson('/api/orders', $this->guestOrderPayload($product, [
            'discount_code' => 'Bienvenue10',
        ]))->assertCreated();

        $order = Order::latest('id')->first();
        $this->assertEquals(2000, $order->discount_total);
        $this->assertEquals(18000, $order->total);
        $this->assertSame('BIENVENUE10', $order->discount_code);
        $this->assertSame(1, Discount::first()->used_count);
    }

    public function test_discount_code_below_minimum_is_refused_with_the_reason(): void
    {
        Discount::create(['code' => 'GROS', 'type' => 'fixed', 'value' => 5000, 'min_subtotal' => 50000]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 10000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['discount_code' => 'GROS']))
            ->assertStatus(422)
            ->assertJsonPath('message', fn($m) => str_contains($m, '50 000'));

        $this->assertSame(0, Order::count());
    }

    public function test_exhausted_discount_code_is_refused(): void
    {
        Discount::create(['code' => 'UNIQUE', 'type' => 'percent', 'value' => 50, 'max_uses' => 1, 'used_count' => 1]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 10000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['discount_code' => 'UNIQUE']))
            ->assertStatus(422)
            ->assertJsonPath('message', fn($m) => str_contains($m, 'maximum'));
    }

    public function test_fixed_discount_never_exceeds_the_subtotal(): void
    {
        Discount::create(['code' => 'CADEAU', 'type' => 'fixed', 'value' => 50000]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 3000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['discount_code' => 'CADEAU']))
            ->assertCreated();

        $order = Order::latest('id')->first();
        $this->assertEquals(3000, $order->discount_total);
        $this->assertEquals(0, $order->total);
    }

    public function test_cancelling_an_order_releases_stock_and_the_discount_code(): void
    {
        Discount::create(['code' => 'REMISE', 'type' => 'percent', 'value' => 10, 'max_uses' => 1]);
        $product = Product::factory()->create(['stock' => 5, 'price' => 10000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['discount_code' => 'REMISE']))
            ->assertCreated();
        $order = Order::latest('id')->first();

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, Discount::first()->used_count);

        $this->actingAs($this->staff(Role::ADMIN), 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, Discount::first()->used_count);
    }

    public function test_quote_endpoint_previews_totals_without_creating_anything(): void
    {
        $zone    = ShippingZone::create(['name' => 'Centre', 'fee' => 1000]);
        Discount::create(['code' => 'PROMO5', 'type' => 'percent', 'value' => 5]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 20000, 'discount' => 10]);

        $response = $this->postJson('/api/orders/quote', [
            'items'            => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_zone_id' => $zone->id,
            'discount_code'    => 'PROMO5',
        ])->assertOk();

        $this->assertEquals(36000, $response->json('data.subtotal'));      // 2 x 18 000 (remise produit 10 %)
        $this->assertEquals(1800, $response->json('data.discount_total')); // 5 % du sous-total
        $this->assertEquals(1000, $response->json('data.shipping_fee'));
        $this->assertEquals(35200, $response->json('data.total'));

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_quote_reports_a_bad_code_without_failing_the_request(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 5000]);

        $response = $this->postJson('/api/orders/quote', [
            'items'         => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_code' => 'NEXISTEPAS',
        ])->assertOk();

        $this->assertEquals(5000, $response->json('data.total'));
        $this->assertEquals(0, $response->json('data.discount_total'));
        $this->assertNotNull($response->json('data.discount_error'));
    }

    public function test_client_cannot_forge_the_total(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 50000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, [
            'total'          => 1,       // tentative de fixer le montant
            'subtotal'       => 1,
            'discount_total' => 49999,
        ]))->assertCreated();

        // Les montants envoyes par le client sont ignores
        $this->assertEquals(50000, Order::latest('id')->first()->total);
    }

    // ═══════════════════════════════════════════════════
    // 7 — RÉGLAGES
    // ═══════════════════════════════════════════════════

    public function test_settings_are_persisted_and_served_publicly(): void
    {
        $this->actingAs($this->staff(Role::ADMIN), 'sanctum')
            ->putJson('/api/admin/settings', [
                'company_name'  => 'RT Water Solution SARL',
                'company_phone' => '+237 677 00 11 22',
                'currency'      => 'XAF',
            ])->assertOk();

        // Persistance reelle : la valeur survit a la requete
        $this->assertSame('RT Water Solution SARL', Setting::get('company_name'));

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'RT Water Solution SARL')
            ->assertJsonPath('data.company_phone', '+237 677 00 11 22');
    }

    public function test_public_settings_hide_internal_keys(): void
    {
        $data = $this->getJson('/api/settings')->assertOk()->json('data');

        $this->assertArrayHasKey('company_name', $data);
        $this->assertArrayNotHasKey('notification_email', $data);
        $this->assertArrayNotHasKey('low_stock_alert', $data);
    }

    public function test_unknown_setting_keys_are_rejected(): void
    {
        $this->actingAs($this->staff(Role::ADMIN), 'sanctum')
            ->putJson('/api/admin/settings', ['stripe_secret_key' => 'sk_live_xxx'])
            ->assertOk();

        $this->assertDatabaseMissing('settings', ['key' => 'stripe_secret_key']);
    }

    public function test_products_per_page_follows_the_setting(): void
    {
        Product::factory()->count(10)->create(['is_available' => true]);
        Setting::put(['products_per_page' => 4]);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(4, 'data');
    }

    // ═══════════════════════════════════════════════════
    // 3 — PERMISSIONS PAR RÔLE
    // ═══════════════════════════════════════════════════

    public function test_login_returns_server_side_permissions(): void
    {
        $user = $this->staff(Role::GESTIONNAIRE);
        $user->update(['password' => bcrypt('secret1234')]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret1234'])
            ->assertOk();

        $permissions = $response->json('user.permissions');
        $this->assertContains('manage_products', $permissions);
        $this->assertNotContains('manage_settings', $permissions);
        $this->assertTrue($response->json('user.is_staff'));
    }

    public function test_manager_can_reach_products_but_not_settings(): void
    {
        $manager = $this->staff(Role::GESTIONNAIRE);

        $this->actingAs($manager, 'sanctum')->getJson('/api/admin/customers')->assertOk();
        $this->actingAs($manager, 'sanctum')->getJson('/api/admin/settings')->assertForbidden();
    }

    public function test_accountant_sees_figures_but_cannot_touch_the_catalogue(): void
    {
        $accountant = $this->staff(Role::COMPTABLE);

        $this->actingAs($accountant, 'sanctum')->getJson('/api/admin/analytics')->assertOk();
        $this->actingAs($accountant, 'sanctum')
            ->postJson('/api/products', ['name' => 'X', 'price' => 1, 'stock' => 1])
            ->assertForbidden();
    }

    public function test_plain_customer_has_no_backoffice_access(): void
    {
        $client = $this->staff(Role::USER);

        $this->actingAs($client, 'sanctum')->getJson('/api/admin/orders')->assertForbidden();
        $this->actingAs($client, 'sanctum')->getJson('/api/admin/customers')->assertForbidden();
        $this->assertFalse(\App\Support\Permissions::isStaff($client));
    }

    // ═══════════════════════════════════════════════════
    // 5 — FICHES CLIENTS
    // ═══════════════════════════════════════════════════

    public function test_customer_list_excludes_staff_and_aggregates_spending(): void
    {
        $admin   = $this->staff(Role::ADMIN);
        $product = Product::factory()->create(['stock' => 20, 'price' => 7000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, [
            'email' => 'awa@client.cm',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]))->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/customers')->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('awa@client.cm', $emails);
        $this->assertNotContains($admin->email, $emails);

        $client = collect($response->json('data'))->firstWhere('email', 'awa@client.cm');
        $this->assertSame(1, $client['orders_count']);
        $this->assertEquals(14000, $client['total_spent']);
    }

    public function test_customer_detail_returns_order_history(): void
    {
        $admin   = $this->staff(Role::ADMIN);
        $product = Product::factory()->create(['stock' => 20, 'price' => 3000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['email' => 'awa@client.cm']))
            ->assertCreated();
        $client = User::where('email', 'awa@client.cm')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/customers/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonCount(1, 'data.orders');

        $this->assertEquals(3000, $response->json('data.total_spent'));
    }

    public function test_staff_account_is_not_exposed_as_a_customer(): void
    {
        $admin = $this->staff(Role::ADMIN);

        $this->actingAs($admin, 'sanctum')->getJson("/api/admin/customers/{$admin->id}")
            ->assertNotFound();
    }

    public function test_customer_export_is_a_csv_with_a_bom(): void
    {
        $admin = $this->staff(Role::ADMIN);
        $product = Product::factory()->create(['stock' => 5, 'price' => 1000]);
        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['email' => 'awa@client.cm']))->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')->get('/api/admin/customers/export')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // sinon Excel casse les accents
        $this->assertStringContainsString('awa@client.cm', $csv);
    }

    // ═══════════════════════════════════════════════════
    // 2 — NEWSLETTER
    // ═══════════════════════════════════════════════════

    public function test_newsletter_signup_is_stored(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'Awa@Client.CM'])->assertCreated();

        // L'adresse est normalisee en minuscules
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'awa@client.cm']);
    }

    public function test_newsletter_signup_twice_does_not_duplicate(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'awa@client.cm'])->assertCreated();
        $this->postJson('/api/newsletter', ['email' => 'awa@client.cm'])->assertOk();

        $this->assertSame(1, NewsletterSubscriber::count());
    }

    public function test_newsletter_resubscribe_reactivates(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'awa@client.cm'])->assertCreated();
        $this->deleteJson('/api/newsletter', ['email' => 'awa@client.cm'])->assertOk();
        $this->assertNotNull(NewsletterSubscriber::first()->unsubscribed_at);

        $this->postJson('/api/newsletter', ['email' => 'awa@client.cm'])->assertCreated();
        $this->assertNull(NewsletterSubscriber::first()->unsubscribed_at);
    }

    public function test_newsletter_rejects_an_invalid_address(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'pas-une-adresse'])->assertStatus(422);
        $this->assertSame(0, NewsletterSubscriber::count());
    }

    // ═══════════════════════════════════════════════════
    // 8 — ADMINISTRATION DES REMISES ET ZONES
    // ═══════════════════════════════════════════════════

    public function test_admin_creates_a_discount_code(): void
    {
        $this->actingAs($this->staff(Role::ADMIN), 'sanctum')
            ->postJson('/api/admin/discounts', [
                'code' => 'rentree2026', 'type' => 'percent', 'value' => 15, 'min_subtotal' => 10000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'RENTREE2026')
            ->assertJsonPath('data.state', 'running');
    }

    public function test_used_discount_is_deactivated_rather_than_deleted(): void
    {
        $admin    = $this->staff(Role::ADMIN);
        $discount = Discount::create(['code' => 'DEJA', 'type' => 'percent', 'value' => 10, 'used_count' => 3]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/discounts/{$discount->id}")->assertOk();

        // L'historique des commandes doit rester lisible
        $this->assertDatabaseHas('discounts', ['id' => $discount->id, 'is_active' => false]);
    }

    public function test_zone_used_by_orders_is_deactivated_rather_than_deleted(): void
    {
        $admin   = $this->staff(Role::ADMIN);
        $zone    = ShippingZone::create(['name' => 'Centre', 'fee' => 1000]);
        $product = Product::factory()->create(['stock' => 5, 'price' => 2000]);

        $this->postJson('/api/orders', $this->guestOrderPayload($product, ['shipping_zone_id' => $zone->id]))
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/shipping-zones/{$zone->id}")->assertOk();

        $this->assertDatabaseHas('shipping_zones', ['id' => $zone->id, 'is_active' => false]);
    }

    public function test_discount_check_endpoint_answers_the_storefront(): void
    {
        Discount::create(['code' => 'PROMO', 'type' => 'fixed', 'value' => 2000]);

        $response = $this->postJson('/api/discounts/check', ['code' => 'promo', 'subtotal' => 15000])
            ->assertOk();
        $this->assertEquals(2000, $response->json('data.amount'));

        $this->postJson('/api/discounts/check', ['code' => 'INCONNU', 'subtotal' => 15000])
            ->assertStatus(422);
    }
}
