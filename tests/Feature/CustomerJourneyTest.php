<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\QuoteReceivedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Parcours client : commande, suivi, e-mails, profil, mot de passe oublié. */
class CustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function guestOrder(int $stock = 10, int $qty = 2): Order
    {
        $product = Product::factory()->create(['stock' => $stock, 'price' => 15000, 'is_available' => true]);

        $this->postJson('/api/orders', [
            'name'    => 'Awa Client',
            'email'   => 'awa@client.cm',
            'phone'   => '+237 690 11 22 33',
            'address' => 'Bastos, Yaoundé',
            'items'   => [['product_id' => $product->id, 'quantity' => $qty]],
        ])->assertCreated()->assertJsonPath('data.reference', fn($ref) => str_starts_with($ref, 'CMD-'));

        return Order::latest('id')->first();
    }

    public function test_order_sends_confirmation_email(): void
    {
        Notification::fake();

        $order = $this->guestOrder();

        Notification::assertSentTo($order->user, OrderPlacedNotification::class);
    }

    public function test_guest_without_email_receives_nothing_and_order_still_succeeds(): void
    {
        Notification::fake();
        $product = Product::factory()->create(['stock' => 5, 'is_available' => true]);

        $this->postJson('/api/orders', [
            'name' => 'Sans Mail', 'phone' => '699000000', 'address' => 'Douala',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_order_can_be_tracked_with_reference_and_phone_or_email(): void
    {
        $order = $this->guestOrder();

        $this->getJson('/api/orders/track?reference=' . $order->reference . '&contact=690112233')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/orders/track?reference=' . $order->id . '&contact=AWA@client.cm')
            ->assertOk();
    }

    public function test_tracking_refuses_wrong_contact(): void
    {
        $order = $this->guestOrder();

        $this->getJson('/api/orders/track?reference=' . $order->reference . '&contact=600000000')
            ->assertNotFound();
    }

    public function test_cancelling_restores_stock_and_notifies(): void
    {
        Notification::fake();
        $order   = $this->guestOrder(stock: 10, qty: 3);
        $product = $order->items()->first()->product;
        $this->assertSame(7, $product->fresh()->stock);

        $admin = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(10, $product->fresh()->stock);
        Notification::assertSentTo($order->user, OrderStatusChangedNotification::class);
    }

    public function test_quote_request_sends_acknowledgement(): void
    {
        Notification::fake();

        $this->postJson('/api/quotes', ['name' => 'Société X', 'email' => 'achat@societe-x.cm'])
            ->assertCreated();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            QuoteReceivedNotification::class,
            fn($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'achat@societe-x.cm'
        );
    }

    public function test_profile_and_password_can_be_updated(): void
    {
        $user = User::factory()->create(['password' => Hash::make('ancien-mdp-123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me', ['name' => 'Nouveau Nom', 'email' => $user->email, 'phone' => '677000000'])
            ->assertOk()
            ->assertJsonPath('data.phone', '677000000');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/password', [
                'current_password' => 'mauvais', 'password' => 'nouveau-mdp-123', 'password_confirmation' => 'nouveau-mdp-123',
            ])->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/password', [
                'current_password' => 'ancien-mdp-123', 'password' => 'nouveau-mdp-123', 'password_confirmation' => 'nouveau-mdp-123',
            ])->assertOk();

        $this->assertTrue(Hash::check('nouveau-mdp-123', $user->fresh()->password));
    }

    public function test_forgot_password_link_points_to_frontend_and_reset_works(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        // Même réponse pour un e-mail inconnu (pas d'énumération des comptes)
        $this->postJson('/api/forgot-password', ['email' => 'inconnu@nulle-part.cm'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);
            $this->assertStringStartsWith(rtrim(config('app.frontend_url'), '/') . '/reset-password?token=', $mail->actionUrl);

            $this->postJson('/api/reset-password', [
                'token' => $n->token, 'email' => $user->email,
                'password' => 'tout-neuf-456', 'password_confirmation' => 'tout-neuf-456',
            ])->assertOk();

            return true;
        });

        $this->assertTrue(Hash::check('tout-neuf-456', $user->fresh()->password));
    }

    public function test_guest_can_poll_order_status_with_signed_link_only(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'is_available' => true]);

        $statusUrl = $this->postJson('/api/orders', [
            'name' => 'Invité', 'phone' => '699112233', 'address' => 'Douala',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('status_url');

        $this->getJson($statusUrl)->assertOk()->assertJsonPath('data.status', 'pending');

        // Lien falsifié pour viser la commande de quelqu'un d'autre : refusé
        $other = $this->guestOrder();
        $this->getJson(preg_replace('#/orders/\d+/#', "/orders/{$other->id}/", $statusUrl))->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'x@y.cm', 'password' => 'faux-mdp-1'])->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'x@y.cm', 'password' => 'faux-mdp-1'])->assertStatus(429);
    }

    public function test_sitemap_lists_active_products(): void
    {
        $product = Product::factory()->create(['name' => 'Filtre Osmose 5 étages', 'is_available' => true]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/shop/' . $product->id . '-filtre-osmose-5-etages', false);
    }
}
