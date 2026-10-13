<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Import massif de produits : lecture du fichier, aperçu, images, enregistrement. */
class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin, 'sanctum');
    }

    /** Construit un .xlsx temporaire à partir de lignes brutes. */
    private function sheet(array $rows, string $name = 'produits.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, $name, null, null, true);
    }

    /** PNG 1×1 valide, pour vérifier la conversion WebP. */
    private function pngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );
    }

    private function zipOf(array $files, string $name = 'images.zip'): UploadedFile
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

    private function analyze(UploadedFile $sheet, array $payload = []): array
    {
        $response = $this->post('/api/admin/products/import/analyze', array_merge(['file' => $sheet], $payload));
        $response->assertOk();

        return $response->json('data');
    }

    // ═══════════════════════════════════════════════════

    public function test_template_is_downloadable_xlsx(): void
    {
        Category::create(['name' => 'Bouteilles', 'slug' => 'bouteilles', 'type' => 'product']);

        $response = $this->get('/api/admin/products/import/template');

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_non_admin_cannot_reach_the_importer(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->get('/api/admin/products/import/template')
            ->assertForbidden();
    }

    public function test_analyze_maps_french_headers_and_reports_actions_without_writing(): void
    {
        Category::create(['name' => 'Bouteilles', 'slug' => 'bouteilles', 'type' => 'product']);
        Product::factory()->create(['name' => 'Bidon 10 L', 'sku' => 'RTW-D10', 'price' => 5000, 'stock' => 4]);

        $preview = $this->analyze($this->sheet([
            ['Nom du produit', 'Référence', 'Catégorie', 'Prix de vente', 'Quantité', 'Disponible'],
            ['Bouteille 1,5 L', 'RTW-B15', 'Bouteilles', '3 500', '120', 'oui'],
            ['Bidon 10 L', 'RTW-D10', 'Bidons', '6000,50', '60', 'non'],
        ]));

        $this->assertSame(['name', 'sku', 'category', 'price', 'stock', 'is_available'], array_keys($preview['mapping']));
        $this->assertSame(2, $preview['summary']['total']);
        $this->assertSame(1, $preview['summary']['create']);
        $this->assertSame(1, $preview['summary']['update']);
        $this->assertSame(0, $preview['summary']['error']);
        $this->assertSame(['Bidons'], $preview['summary']['new_categories']);

        // Séparateurs de milliers et décimales françaises
        $this->assertEquals(3500, $preview["rows"][0]["product"]["price"]);
        $this->assertEquals(6000.5, $preview["rows"][1]["product"]["price"]);
        $this->assertTrue($preview['rows'][0]['product']['is_available']);
        $this->assertFalse($preview['rows'][1]['product']['is_available']);

        // L'aperçu n'écrit rien
        $this->assertSame(1, Product::count());
        $this->assertSame(1, Category::count());
    }

    public function test_analyze_rejects_file_without_required_columns(): void
    {
        $this->post('/api/admin/products/import/analyze', [
            'file' => $this->sheet([['Couleur', 'Poids'], ['Bleu', '2kg']]),
        ])->assertStatus(422)->assertJsonPath('message', fn($m) => str_contains($m, 'name'));
    }

    public function test_invalid_rows_are_flagged_and_skipped_at_commit(): void
    {
        $preview = $this->analyze($this->sheet([
            ['nom', 'prix', 'stock'],
            ['Produit valide', '1000', '5'],
            ['', '1000', '5'],            // nom manquant
            ['Prix illisible', 'gratuit', '2'],
            ['Stock négatif', '1000', '-4'],
        ]));

        $this->assertSame(3, $preview['summary']['error']);
        $this->assertSame('error', $preview['rows'][1]['action']);
        $this->assertNotEmpty($preview['rows'][1]['errors']);

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.failed_count', 3);

        $this->assertSame(['Produit valide'], Product::pluck('name')->all());
    }

    public function test_duplicate_reference_inside_the_file_is_rejected(): void
    {
        $preview = $this->analyze($this->sheet([
            ['nom', 'reference', 'prix'],
            ['Premier', 'DUP-1', '1000'],
            ['Doublon', 'DUP-1', '2000'],
        ]));

        $this->assertSame('create', $preview['rows'][0]['action']);
        $this->assertSame('error', $preview['rows'][1]['action']);
        $this->assertStringContainsString('ligne 2', $preview['rows'][1]['errors'][0]);
    }

    public function test_commit_creates_products_categories_and_stock_movements(): void
    {
        $preview = $this->analyze($this->sheet([
            ['nom', 'reference', 'categorie', 'prix', 'prix_achat', 'stock', 'stock_min', 'emplacement'],
            ['Fontaine bureau', 'RTW-FT01', 'Équipements', '185000', '140000', '8', '2', 'Showroom'],
        ]));

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1);

        $product = Product::firstWhere('sku', 'RTW-FT01');
        $this->assertSame('Fontaine bureau', $product->name);
        $this->assertSame('185000.00', $product->price);
        $this->assertSame(8, $product->stock);
        $this->assertSame(2, $product->min_quantity);
        $this->assertSame('Showroom', $product->location);
        $this->assertSame('Équipements', $product->category->name);
        $this->assertSame('equipements', $product->category->slug);

        $movement = StockMovement::firstWhere('product_id', $product->id);
        $this->assertSame('in', $movement->type);
        $this->assertSame(8, $movement->quantity);
        $this->assertSame('import', $movement->reason);
        $this->assertSame($this->admin->id, $movement->user_id);
    }

    public function test_existing_product_is_updated_and_empty_columns_are_preserved(): void
    {
        $product = Product::factory()->create([
            'name'        => 'Ancien nom',
            'sku'         => 'RTW-B15',
            'price'       => 3000,
            'stock'       => 10,
            'description' => 'Description à conserver',
            'location'    => 'Entrepôt A',
        ]);

        $preview = $this->analyze($this->sheet([
            ['nom', 'reference', 'prix', 'stock', 'description'],
            ['Nouveau nom', 'RTW-B15', '4200', '25', ''],
        ]));

        $this->assertSame('update', $preview['rows'][0]['action']);
        $this->assertSame($product->id, $preview['rows'][0]['existing']['id']);

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.updated_count', 1);

        $product->refresh();
        $this->assertSame('Nouveau nom', $product->name);
        $this->assertSame('4200.00', $product->price);
        $this->assertSame(25, $product->stock);
        $this->assertSame('Description à conserver', $product->description);
        $this->assertSame('Entrepôt A', $product->location);
        $this->assertSame(1, Product::count());

        $movement = StockMovement::firstWhere('product_id', $product->id);
        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(15, $movement->quantity);
    }

    public function test_update_can_be_declined_so_only_new_products_are_created(): void
    {
        Product::factory()->create(['sku' => 'RTW-B15', 'price' => 3000]);

        $preview = $this->analyze($this->sheet([
            ['nom', 'reference', 'prix'],
            ['Existant', 'RTW-B15', '9999'],
            ['Nouveau', 'RTW-NEW', '1000'],
        ]));

        $this->post('/api/admin/products/import/commit', [
            'token'           => $preview['token'],
            'update_existing' => false,
        ])->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertSame('3000.00', Product::firstWhere('sku', 'RTW-B15')->price);
    }

    public function test_image_named_in_the_sheet_is_taken_from_the_zip_archive(): void
    {
        $preview = $this->analyze(
            $this->sheet([
                ['nom', 'reference', 'prix', 'image'],
                ['Bouteille 1,5 L', 'RTW-B15', '3500', 'bouteille.png'],
            ]),
            ['archive' => $this->zipOf(['photos/bouteille.png' => $this->pngBytes()])]
        );

        $this->assertSame('archive', $preview['rows'][0]['image']['source']);
        $this->assertSame('bouteille.png', $preview['rows'][0]['image']['ref']);
        $this->assertSame(1, $preview['summary']['images']);

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.images_count', 1);

        $path = Product::firstWhere('sku', 'RTW-B15')->image_url;
        $this->assertStringStartsWith('products/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_image_is_matched_automatically_by_reference_then_by_product_name(): void
    {
        $preview = $this->analyze(
            $this->sheet([
                ['nom', 'reference', 'prix'],
                ['Bouteille 1,5 L', 'RTW-B15', '3500'],   // retrouvé par référence
                ['Bidon 10 Litres', '', '6000'],          // retrouvé par nom slugifié
                ['Sans visuel', 'RTW-XX', '1000'],
            ]),
            ['archive' => $this->zipOf([
                'RTW-B15.jpg'            => $this->pngBytes(),
                'bidon-10-litres-1.png'  => $this->pngBytes(), // suffixe de série accepté
            ])]
        );

        $this->assertSame('archive', $preview['rows'][0]['image']['source']);
        $this->assertSame('RTW-B15.jpg', $preview['rows'][0]['image']['ref']);
        $this->assertSame('archive', $preview['rows'][1]['image']['source']);
        $this->assertSame('bidon-10-litres-1.png', $preview['rows'][1]['image']['ref']);
        $this->assertSame('none', $preview['rows'][2]['image']['source']);

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.images_count', 2);

        $this->assertNotNull(Product::firstWhere('sku', 'RTW-B15')->image_url);
        $this->assertNotNull(Product::firstWhere('name', 'Bidon 10 Litres')->image_url);
        $this->assertNull(Product::firstWhere('sku', 'RTW-XX')->image_url);
    }

    public function test_existing_storage_path_is_reused_instead_of_being_duplicated(): void
    {
        Storage::disk('public')->put('products/deja-la.webp', $this->pngBytes());

        $preview = $this->analyze($this->sheet([
            ['nom', 'prix', 'image'],
            ['Produit', '1000', 'products/deja-la.webp'],
        ]));

        $this->assertSame('existing', $preview['rows'][0]['image']['source']);

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])->assertCreated();

        $this->assertSame('products/deja-la.webp', Product::first()->image_url);
        $this->assertCount(1, Storage::disk('public')->files('products'));
    }

    public function test_bare_filename_prefers_the_uploaded_archive_over_the_storage_root(): void
    {
        Storage::disk('public')->put('bouteille.png', 'ancien fichier');

        $preview = $this->analyze(
            $this->sheet([
                ['nom', 'prix', 'image'],
                ['Produit', '1000', 'bouteille.png'],
            ]),
            ['archive' => $this->zipOf(['bouteille.png' => $this->pngBytes()])]
        );

        $this->assertSame('archive', $preview['rows'][0]['image']['source']);
    }

    public function test_missing_image_is_warned_but_does_not_block_the_row(): void
    {
        $preview = $this->analyze($this->sheet([
            ['nom', 'prix', 'image'],
            ['Produit', '1000', 'absente.jpg'],
        ]));

        $this->assertSame('create', $preview['rows'][0]['action']);
        $this->assertSame('none', $preview['rows'][0]['image']['source']);
        $this->assertStringContainsString('absente.jpg', implode(' ', $preview['warnings']));
    }

    public function test_remote_image_url_is_downloaded_and_converted(): void
    {
        Http::fake([
            'cdn.test/*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);

        $preview = $this->analyze($this->sheet([
            ['nom', 'prix', 'image'],
            ['Fontaine', '185000', 'https://cdn.test/fontaine.png'],
        ]));

        $this->assertSame('url', $preview['rows'][0]['image']['source']);

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.images_count', 1);

        Storage::disk('public')->assertExists(Product::first()->image_url);
    }

    public function test_remote_download_can_be_disabled(): void
    {
        Http::fake();

        $preview = $this->analyze($this->sheet([
            ['nom', 'prix', 'image'],
            ['Fontaine', '185000', 'https://cdn.test/fontaine.png'],
        ]));

        $this->post('/api/admin/products/import/commit', [
            'token'               => $preview['token'],
            'allow_remote_images' => false,
        ])->assertCreated()->assertJsonPath('data.images_count', 0);

        Http::assertNothingSent();
        $this->assertNull(Product::first()->image_url);
    }

    public function test_non_image_url_is_ignored_without_failing_the_row(): void
    {
        Http::fake([
            'cdn.test/*' => Http::response('<html>404</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $preview = $this->analyze($this->sheet([
            ['nom', 'prix', 'image'],
            ['Fontaine', '185000', 'https://cdn.test/page.html'],
        ]));

        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.images_count', 0);

        $this->assertNull(Product::first()->image_url);
    }

    public function test_archive_entries_cannot_escape_the_import_directory(): void
    {
        $preview = $this->analyze(
            $this->sheet([['nom', 'prix'], ['Produit', '1000']]),
            ['archive' => $this->zipOf([
                '../../evil.png'    => $this->pngBytes(),
                'notes.txt'         => 'texte',
                '__MACOSX/._x.png'  => 'junk',
            ])]
        );

        // Seule l'image est retenue, et sous son nom de base
        $this->assertSame(1, $preview['images']['available']);
        $this->assertSame(1, $preview['images']['uploaded']);
        $this->assertCount(
            1,
            Storage::disk('local')->files('imports/' . $preview['token'] . '/images')
        );
        $this->assertSame(
            'evil.png',
            basename(Storage::disk('local')->files('imports/' . $preview['token'] . '/images')[0])
        );
    }

    public function test_forged_token_is_rejected(): void
    {
        $this->postJson('/api/admin/products/import/commit', ['token' => '../../../etc/passwd'])
            ->assertStatus(422);

        $this->postJson('/api/admin/products/import/commit', ['token' => str_repeat('a', 32)])
            ->assertStatus(422)
            ->assertJsonPath('message', fn($m) => str_contains($m, 'expiré') || str_contains($m, 'introuvable'));
    }

    public function test_discard_removes_the_uploaded_files(): void
    {
        $preview = $this->analyze($this->sheet([['nom', 'prix'], ['Produit', '1000']]));

        $this->postJson('/api/admin/products/import/discard', ['token' => $preview['token']])->assertOk();

        $this->assertEmpty(Storage::disk('local')->allFiles('imports/' . $preview['token']));
    }

    public function test_history_lists_past_imports_with_their_errors(): void
    {
        $preview = $this->analyze($this->sheet([
            ['nom', 'prix'],
            ['Bon produit', '1000'],
            ['', '1000'],
        ]));
        $this->post('/api/admin/products/import/commit', ['token' => $preview['token']])->assertCreated();

        $response = $this->getJson('/api/admin/products/imports')->assertOk();

        $this->assertSame(1, $response->json('pagination.total'));
        $entry = $response->json('data.0');
        $this->assertSame('produits.xlsx', $entry['filename']);
        $this->assertSame(1, $entry['created_count']);
        $this->assertSame(1, $entry['failed_count']);
        $this->assertSame($this->admin->name, $entry['user']);
        $this->assertSame(3, $entry['errors'][0]['line']);
    }

    public function test_csv_files_are_accepted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
        file_put_contents($path, "nom,prix,stock\nProduit CSV,2500,7\n");

        $preview = $this->analyze(new UploadedFile($path, 'produits.csv', null, null, true));

        $this->assertSame(1, $preview['summary']['create']);
        $this->assertEquals(2500, $preview["rows"][0]["product"]["price"]);
    }
}
