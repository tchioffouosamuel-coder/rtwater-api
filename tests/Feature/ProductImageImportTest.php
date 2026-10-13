<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Import d'images en masse : second temps du flux d'import.
 *
 * Le fichier Excel declare « RTW-B15.jpg » dans sa colonne image, les produits
 * sont crees sans visuel, puis les photos arrivent et se rattachent seules.
 */
class ProductImageImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Role::firstOrCreate(['name' => Role::ADMIN], ['label' => 'Administrateur']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::ADMIN);
        $this->actingAs($this->admin, 'sanctum');
    }

    private function pngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );
    }

    /** @param array<string, string> $files */
    private function zipOf(array $files, string $name = 'photos.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($files as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function imageFile(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img') . '.png';
        file_put_contents($path, $this->pngBytes());

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    /** @return array<string, mixed> */
    private function analyze(array $payload): array
    {
        $response = $this->post('/api/admin/products/images/analyze', $payload, ['Accept' => 'application/json']);
        $response->assertOk();

        return $response->json('data');
    }

    // ═══════════════════════════════════════════════════
    // FLUX PRINCIPAL : EXCEL PUIS PHOTOS
    // ═══════════════════════════════════════════════════

    public function test_image_declared_in_the_sheet_is_attached_when_uploaded_later(): void
    {
        // Etat laisse par l'import Excel : le nom est connu, la photo pas encore
        $product = Product::factory()->create([
            'name'           => 'Bouteille 1,5 L',
            'sku'            => 'RTW-B15',
            'image_url'      => null,
            'image_filename' => 'RTW-B15.jpg',
        ]);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['RTW-B15.jpg' => $this->pngBytes()]),
        ]);

        $this->assertSame(1, $preview['summary']['matched']);
        $this->assertSame('filename', $preview['matches'][0]['matched_by']);
        $this->assertSame($product->id, $preview['matches'][0]['product_id']);
        $this->assertFalse($preview['matches'][0]['replaces']);

        // L'apercu n'ecrit rien
        $this->assertNull($product->fresh()->image_url);

        $this->postJson('/api/admin/products/images/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.attached', 1);

        $product->refresh();
        $this->assertNotNull($product->image_url);
        Storage::disk('public')->assertExists($product->image_url);

        // Le nom attendu est efface : le produit n'est plus « en attente »
        $this->assertNull($product->image_filename);
    }

    public function test_photos_in_subfolders_of_the_archive_are_found(): void
    {
        Product::factory()->create(['image_url' => null, 'image_filename' => 'RTW-D10.jpg']);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['export/photos/RTW-D10.jpg' => $this->pngBytes()]),
        ]);

        $this->assertSame(1, $preview['summary']['matched']);
    }

    public function test_extension_in_the_sheet_may_differ_from_the_actual_file(): void
    {
        // L'admin a ecrit « RTW-B15.jpg », le photographe a livre un .png
        Product::factory()->create(['image_url' => null, 'image_filename' => 'RTW-B15.jpg']);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['RTW-B15.png' => $this->pngBytes()]),
        ]);

        $this->assertSame(1, $preview['summary']['matched']);
        $this->assertSame('filename', $preview['matches'][0]['matched_by']);
    }

    public function test_loose_image_files_work_without_an_archive(): void
    {
        Product::factory()->create(['image_url' => null, 'image_filename' => 'RTW-FT01.png']);

        $preview = $this->analyze(['images' => [$this->imageFile('RTW-FT01.png')]]);

        $this->assertSame(1, $preview['summary']['matched']);
    }

    // ═══════════════════════════════════════════════════
    // AUTRES CLÉS DE RATTACHEMENT
    // ═══════════════════════════════════════════════════

    public function test_fallback_matching_by_sku_then_by_product_name(): void
    {
        $bySku  = Product::factory()->create(['name' => 'Bidon 10 L', 'sku' => 'RTW-D10', 'image_url' => null]);
        $byName = Product::factory()->create(['name' => 'Fontaine Bureau', 'sku' => null, 'image_url' => null]);

        $preview = $this->analyze([
            'archive' => $this->zipOf([
                'RTW-D10.jpg'        => $this->pngBytes(),
                'fontaine-bureau.jpg' => $this->pngBytes(),
            ]),
        ]);

        $by = collect($preview['matches'])->keyBy('product_id');
        $this->assertSame('sku', $by[$bySku->id]['matched_by']);
        $this->assertSame('name', $by[$byName->id]['matched_by']);
    }

    public function test_declared_filename_wins_over_the_sku(): void
    {
        // Le nom declare est plus fiable : c'est l'admin qui l'a ecrit
        $product = Product::factory()->create([
            'sku'            => 'RTW-B15',
            'image_url'      => null,
            'image_filename' => 'photo-speciale.jpg',
        ]);

        $preview = $this->analyze([
            'archive' => $this->zipOf([
                'photo-speciale.jpg' => $this->pngBytes(),
                'RTW-B15.jpg'        => $this->pngBytes(),
            ]),
        ]);

        $match = collect($preview['matches'])->firstWhere('product_id', $product->id);
        $this->assertSame('photo-speciale.jpg', $match['file']);
        $this->assertSame('filename', $match['matched_by']);
    }

    public function test_series_suffix_is_accepted(): void
    {
        Product::factory()->create(['name' => 'Bidon 10 L', 'sku' => 'RTW-D10', 'image_url' => null]);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['RTW-D10-1.jpg' => $this->pngBytes()]),
        ]);

        $this->assertSame(1, $preview['summary']['matched']);
    }

    public function test_one_file_is_never_attached_to_two_products(): void
    {
        // Deux produits de meme nom : le premier prend la photo, pas les deux
        Product::factory()->create(['name' => 'Bidon 10 L', 'sku' => 'A1', 'image_url' => null]);
        Product::factory()->create(['name' => 'Bidon 10 L', 'sku' => 'A2', 'image_url' => null]);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['bidon-10-l.jpg' => $this->pngBytes()]),
        ]);

        $this->assertSame(1, $preview['summary']['matched']);
        $this->assertSame(0, $preview['summary']['unmatched']);
    }

    public function test_product_awaiting_an_image_has_priority_over_a_name_match(): void
    {
        $awaiting = Product::factory()->create([
            'name' => 'Autre produit', 'sku' => null,
            'image_url' => null, 'image_filename' => 'bidon-10-l.jpg',
        ]);
        Product::factory()->create(['name' => 'Bidon 10 L', 'sku' => null, 'image_url' => null]);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['bidon-10-l.jpg' => $this->pngBytes()]),
        ]);

        // Celui qui a nomme le fichier le reclame en premier
        $this->assertSame(1, $preview['summary']['matched']);
        $this->assertSame($awaiting->id, $preview['matches'][0]['product_id']);
    }

    // ═══════════════════════════════════════════════════
    // REMPLACEMENT, FICHIERS ORPHELINS
    // ═══════════════════════════════════════════════════

    public function test_existing_image_is_flagged_as_a_replacement(): void
    {
        Storage::disk('public')->put('products/ancienne.webp', $this->pngBytes());
        $product = Product::factory()->create([
            'sku' => 'RTW-B15', 'image_url' => 'products/ancienne.webp',
        ]);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['RTW-B15.jpg' => $this->pngBytes()]),
        ]);

        $this->assertTrue($preview['matches'][0]['replaces']);
        $this->assertSame(1, $preview['summary']['replacing']);

        $this->postJson('/api/admin/products/images/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.replaced', 1);

        $product->refresh();
        $this->assertNotSame('products/ancienne.webp', $product->image_url);

        // Pas de fichier orphelin
        Storage::disk('public')->assertMissing('products/ancienne.webp');
        Storage::disk('public')->assertExists($product->image_url);
    }

    public function test_replacement_can_be_declined(): void
    {
        Storage::disk('public')->put('products/ancienne.webp', $this->pngBytes());
        $product = Product::factory()->create([
            'sku' => 'RTW-B15', 'image_url' => 'products/ancienne.webp',
        ]);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['RTW-B15.jpg' => $this->pngBytes()]),
        ]);

        $this->postJson('/api/admin/products/images/commit', [
            'token'            => $preview['token'],
            'replace_existing' => false,
        ])->assertCreated()->assertJsonPath('data.skipped', 1);

        $this->assertSame('products/ancienne.webp', $product->fresh()->image_url);
        Storage::disk('public')->assertExists('products/ancienne.webp');
    }

    // ═══════════════════════════════════════════════════
    // FICHIERS SANS CORRESPONDANCE, SÉCURITÉ
    // ═══════════════════════════════════════════════════

    public function test_unmatched_files_are_listed_not_silently_dropped(): void
    {
        Product::factory()->create(['name' => 'Bidon', 'sku' => 'RTW-D10', 'image_url' => null]);

        $preview = $this->analyze([
            'archive' => $this->zipOf([
                'RTW-D10.jpg'   => $this->pngBytes(),
                'inconnue.jpg'  => $this->pngBytes(),
            ]),
        ]);

        $this->assertSame(1, $preview['summary']['matched']);
        $this->assertSame(['inconnue.jpg'], $preview['unmatched']);
        $this->assertStringContainsString('1 fichier', implode(' ', $preview['warnings']));
    }

    public function test_archive_entries_cannot_escape_the_import_directory(): void
    {
        Product::factory()->create(['image_url' => null, 'image_filename' => 'evil.png']);

        $preview = $this->analyze([
            'archive' => $this->zipOf([
                '../../evil.png'   => $this->pngBytes(),
                'notes.txt'        => 'texte',
                '__MACOSX/._x.png' => 'junk',
            ]),
        ]);

        // Seule l'image est retenue, sous son nom de base
        $this->assertSame(1, $preview['summary']['uploaded']);
        $this->assertCount(
            1,
            Storage::disk('local')->files('imports/' . $preview['token'] . '/images')
        );
    }

    public function test_upload_without_any_file_is_refused(): void
    {
        $this->postJson('/api/admin/products/images/analyze', [])->assertStatus(422);
    }

    public function test_archive_without_images_is_refused_with_a_reason(): void
    {
        $this->post('/api/admin/products/images/analyze', [
            'archive' => $this->zipOf(['notes.txt' => 'texte']),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn($m) => str_contains($m, 'Aucune image'));
    }

    public function test_forged_token_is_rejected(): void
    {
        $this->postJson('/api/admin/products/images/commit', ['token' => '../../../etc/passwd'])
            ->assertStatus(422);

        $this->postJson('/api/admin/products/images/commit', ['token' => str_repeat('a', 32)])
            ->assertStatus(422);
    }

    public function test_discard_removes_the_uploaded_files(): void
    {
        Product::factory()->create(['image_url' => null, 'image_filename' => 'photo.png']);

        $preview = $this->analyze(['images' => [$this->imageFile('photo.png')]]);

        $this->postJson('/api/admin/products/images/discard', ['token' => $preview['token']])->assertOk();

        $this->assertEmpty(Storage::disk('local')->allFiles('imports/' . $preview['token']));
    }

    // ═══════════════════════════════════════════════════
    // PRODUITS EN ATTENTE
    // ═══════════════════════════════════════════════════

    public function test_pending_report_lists_the_filenames_to_prepare(): void
    {
        Product::factory()->create(['name' => 'Bouteille', 'image_url' => null, 'image_filename' => 'RTW-B15.jpg']);
        Product::factory()->create(['name' => 'Bidon', 'image_url' => null, 'image_filename' => 'RTW-D10.jpg']);
        // Deja illustre : hors liste
        Product::factory()->create(['image_url' => 'products/x.webp', 'image_filename' => null]);

        $response = $this->getJson('/api/admin/products/images/pending')->assertOk();

        $this->assertSame(2, $response->json('summary.awaiting'));
        $this->assertEqualsCanonicalizing(
            ['RTW-B15.jpg', 'RTW-D10.jpg'],
            collect($response->json('data'))->pluck('expects')->all(),
        );
    }

    public function test_pending_list_shrinks_after_the_images_are_attached(): void
    {
        Product::factory()->create(['image_url' => null, 'image_filename' => 'RTW-B15.jpg']);
        Product::factory()->create(['image_url' => null, 'image_filename' => 'RTW-D10.jpg']);

        $preview = $this->analyze([
            'archive' => $this->zipOf(['RTW-B15.jpg' => $this->pngBytes()]),
        ]);

        // Le produit non servi reste signale dans l'apercu
        $this->assertSame(1, $preview['summary']['pending']);
        $this->assertSame('RTW-D10.jpg', $preview['pending'][0]['expects']);

        $this->postJson('/api/admin/products/images/commit', ['token' => $preview['token']])->assertCreated();

        $this->getJson('/api/admin/products/images/pending')
            ->assertOk()
            ->assertJsonPath('summary.awaiting', 1);
    }

    // ═══════════════════════════════════════════════════
    // ACCÈS
    // ═══════════════════════════════════════════════════

    public function test_a_manager_can_import_images_but_a_customer_cannot(): void
    {
        Role::firstOrCreate(['name' => Role::GESTIONNAIRE], ['label' => 'Gestionnaire']);
        $manager = User::factory()->create();
        $manager->assignRole(Role::GESTIONNAIRE);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/admin/products/images/pending')
            ->assertOk();

        Role::firstOrCreate(['name' => Role::USER], ['label' => 'Client']);
        $customer = User::factory()->create();
        $customer->assignRole(Role::USER);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/products/images/pending')
            ->assertForbidden();
    }
}
