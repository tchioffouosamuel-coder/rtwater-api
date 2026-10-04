<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cache des contenus, chargement groupé du CMS, images WebP, remontée des erreurs. */
class PerformanceAndMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $content): Page
    {
        return Page::create([
            'title' => ucfirst($slug), 'slug' => $slug, 'content' => $content,
            'is_published' => true, 'sort_order' => 0,
        ]);
    }

    public function test_cms_pages_can_be_loaded_in_one_request(): void
    {
        $this->page('accueil', '{"a":1}');
        $this->page('contact', '{"b":2}');
        $this->page('brouillon', '{}')->update(['is_published' => false]);

        $this->getJson('/api/pages?slugs=accueil,contact,brouillon')
            ->assertOk()
            ->assertJsonPath('data.accueil.content', '{"a":1}')
            ->assertJsonPath('data.contact.content', '{"b":2}')
            ->assertJsonMissingPath('data.brouillon');
    }

    public function test_cached_page_is_refreshed_as_soon_as_it_is_edited(): void
    {
        $page = $this->page('contact', 'ancien');

        $this->getJson('/api/pages/contact')->assertJsonPath('data.content', 'ancien');

        $page->update(['content' => 'nouveau']);

        $this->getJson('/api/pages/contact')->assertJsonPath('data.content', 'nouveau');
        $this->getJson('/api/pages?slugs=contact')->assertJsonPath('data.contact.content', 'nouveau');
    }

    public function test_unpublished_or_missing_page_returns_404(): void
    {
        $this->page('cachee', 'x')->update(['is_published' => false]);

        $this->getJson('/api/pages/cachee')->assertNotFound();
        $this->getJson('/api/pages/inexistante')->assertNotFound();
    }

    public function test_category_counts_follow_product_changes_despite_cache(): void
    {
        $categoryId = \App\Models\Category::factory()->create(['type' => 'product'])->id;
        Product::factory()->create(['category_id' => $categoryId]);

        $count = fn() => collect($this->getJson('/api/categories')->json('data'))
            ->firstWhere('id', $categoryId)['products_count'] ?? null;

        $this->assertSame(1, $count());   // met la liste en cache
        Product::factory()->create(['category_id' => $categoryId]);

        $this->assertSame(2, $count());
    }

    public function test_uploaded_images_are_converted_to_webp(): void
    {
        if (!ImageOptimizer::supported()) {
            $this->markTestSkipped('GD sans WebP sur cette machine');
        }
        Storage::fake('public');
        $admin = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $admin->assignRole('admin');

        $path = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/upload/image', [
                'image'  => UploadedFile::fake()->image('photo.jpg', 3000, 2000),
                'folder' => 'products',
            ])
            ->assertCreated()
            ->json('path');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(ImageOptimizer::MAX_SIZE, max($w, $h));
    }

    public function test_existing_images_can_be_converted_by_command(): void
    {
        if (!ImageOptimizer::supported()) {
            $this->markTestSkipped('GD sans WebP sur cette machine');
        }
        Storage::fake('public');
        $original = UploadedFile::fake()->image('old.png', 800, 600)->store('products', 'public');
        $product = Product::factory()->create(['image_url' => $original]);

        $this->artisan('images:optimize')->assertSuccessful();

        $product->refresh();
        $this->assertStringEndsWith('.webp', $product->image_url);
        Storage::disk('public')->assertExists($product->image_url);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_browser_errors_are_logged(): void
    {
        Log::shouldReceive('channel')->once()->with('client')->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(fn($msg, $ctx) => $msg === 'x is undefined' && $ctx['url'] === '/shop');

        $this->postJson('/api/client-errors', [
            'message' => 'x is undefined', 'url' => '/shop', 'source' => 'onerror',
        ])->assertNoContent();
    }
}
