<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fiche technique produit : texte, image ou PDF. */
class ProductDatasheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Role::firstOrCreate(['name' => Role::ADMIN], ['label' => 'Administrateur']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::ADMIN);
        $this->actingAs($this->admin, 'sanctum');
    }

    /** @return array<string, mixed> */
    private function basePayload(array $extra = []): array
    {
        return array_merge([
            'name'  => 'Osmoseur 5 étapes',
            'price' => 185000,
            'stock' => 4,
        ], $extra);
    }

    /** PNG 1×1 valide : la conversion WebP a besoin d'une vraie image. */
    private function pngFile(string $name = 'specs.png'): UploadedFile
    {
        $binary = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );

        $path = tempnam(sys_get_temp_dir(), 'ds') . '.png';
        file_put_contents($path, $binary);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function pdfFile(string $name = 'fiche-technique.pdf'): UploadedFile
    {
        // En-tête PHP minimal mais valide : Laravel devine le type depuis le contenu
        $path = tempnam(sys_get_temp_dir(), 'ds') . '.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    /**
     * Envoi multipart. L'en-tete Accept est indispensable : sans elle Laravel
     * repond une redirection 302 au lieu d'un 422 sur erreur de validation.
     *
     * @param  array<string, mixed>  $data
     */
    private function multipart(string $uri, array $data)
    {
        return $this->post($uri, $data, ['Accept' => 'application/json']);
    }

    /** Mise a jour multipart : POST + _method=PUT, comme le fait le frontend. */
    private function multipartUpdate(string $uri, array $data)
    {
        return $this->multipart($uri, array_merge($data, ['_method' => 'PUT']));
    }

    // ═══════════════════════════════════════════════════
    // TEXTE
    // ═══════════════════════════════════════════════════

    public function test_text_datasheet_is_stored_and_served(): void
    {
        $specs = "Débit : 10 L/min\nPression : 2 à 6 bar\nFiltration : 5 µm";

        $this->postJson('/api/products', $this->basePayload([
            'datasheet_type' => 'text',
            'datasheet_text' => $specs,
        ]))->assertCreated()
            ->assertJsonPath('data.datasheet.type', 'text')
            ->assertJsonPath('data.datasheet.text', $specs)
            ->assertJsonPath('data.datasheet.url', null);

        $product = Product::firstWhere('name', 'Osmoseur 5 étapes');
        $this->assertSame('text', $product->datasheet_type);
        $this->assertSame($specs, $product->datasheet_text);
        $this->assertNull($product->datasheet_path);
    }

    public function test_text_type_without_content_is_refused(): void
    {
        $this->postJson('/api/products', $this->basePayload(['datasheet_type' => 'text']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('datasheet_text');

        // Choisir « texte » sans rien écrire laisserait une section vide en ligne
        $this->assertSame(0, Product::count());
    }

    // ═══════════════════════════════════════════════════
    // IMAGE
    // ═══════════════════════════════════════════════════

    public function test_image_datasheet_is_stored_as_webp(): void
    {
        $response = $this->multipart('/api/products', $this->basePayload([
            'datasheet_type' => 'image',
            'datasheet_file' => $this->pngFile('tableau-specs.png'),
        ]))->assertCreated();

        $product = Product::first();
        $this->assertSame('image', $product->datasheet_type);
        $this->assertStringStartsWith('datasheets/', $product->datasheet_path);
        Storage::disk('public')->assertExists($product->datasheet_path);

        // Le nom d'origine sert de libellé au lien de téléchargement
        $this->assertSame('tableau-specs.png', $product->datasheet_filename);
        $this->assertStringContainsString('datasheets/', $response->json('data.datasheet.url'));
    }

    public function test_datasheet_image_is_less_downscaled_than_a_product_photo(): void
    {
        // Une fiche technique est un tableau de caractéristiques : réduite comme
        // une photo produit, les petits caractères deviennent illisibles.
        $this->assertGreaterThan(
            \App\Support\ImageOptimizer::MAX_SIZE,
            \App\Support\ImageOptimizer::DATASHEET_MAX_SIZE,
        );
    }

    // ═══════════════════════════════════════════════════
    // PDF
    // ═══════════════════════════════════════════════════

    public function test_pdf_datasheet_is_stored_untouched(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_type' => 'pdf',
            'datasheet_file' => $this->pdfFile('osmoseur-RTW-5E.pdf'),
        ]))->assertCreated()
            ->assertJsonPath('data.datasheet.type', 'pdf')
            ->assertJsonPath('data.datasheet.filename', 'osmoseur-RTW-5E.pdf');

        $product = Product::first();
        $this->assertSame('pdf', $product->datasheet_type);
        $this->assertStringEndsWith('.pdf', $product->datasheet_path);
        Storage::disk('public')->assertExists($product->datasheet_path);
    }

    public function test_file_decides_the_type_over_the_declared_one(): void
    {
        // L'admin annonce « image » mais dépose un PDF : le fichier fait foi
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_type' => 'image',
            'datasheet_file' => $this->pdfFile(),
        ]))->assertCreated()
            ->assertJsonPath('data.datasheet.type', 'pdf');
    }

    public function test_file_without_declared_type_still_works(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pngFile(),
        ]))->assertCreated()
            ->assertJsonPath('data.datasheet.type', 'image');
    }

    // ═══════════════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════════════

    public function test_executable_and_office_files_are_refused(): void
    {
        foreach (['virus.exe' => 'application/octet-stream', 'specs.docx' => 'application/msword'] as $name => $mime) {
            $path = tempnam(sys_get_temp_dir(), 'bad');
            file_put_contents($path, 'contenu quelconque');

            $this->multipart('/api/products', $this->basePayload([
                'datasheet_file' => new UploadedFile($path, $name, $mime, null, true),
            ]))->assertStatus(422)->assertJsonValidationErrors('datasheet_file');
        }

        $this->assertSame(0, Product::count());
    }

    public function test_svg_is_refused_because_it_can_carry_script(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'svg') . '.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => new UploadedFile($path, 'specs.svg', 'image/svg+xml', null, true),
        ]))->assertStatus(422)->assertJsonValidationErrors('datasheet_file');
    }

    public function test_unknown_datasheet_type_is_refused(): void
    {
        $this->postJson('/api/products', $this->basePayload(['datasheet_type' => 'video']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('datasheet_type');
    }

    // ═══════════════════════════════════════════════════
    // MISE À JOUR
    // ═══════════════════════════════════════════════════

    public function test_updating_the_price_keeps_the_existing_datasheet(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pdfFile('original.pdf'),
        ]))->assertCreated();

        $product = Product::first();
        $path = $product->datasheet_path;

        // Mise à jour qui ne parle pas de fiche technique
        $this->putJson("/api/products/{$product->id}", ['price' => 199000])->assertOk();

        $product->refresh();
        $this->assertSame('pdf', $product->datasheet_type);
        $this->assertSame($path, $product->datasheet_path);
        $this->assertSame('original.pdf', $product->datasheet_filename);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_the_file_removes_the_previous_one(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pdfFile('ancienne.pdf'),
        ]))->assertCreated();

        $product = Product::first();
        $oldPath = $product->datasheet_path;

        $this->multipartUpdate("/api/products/{$product->id}", [
            'datasheet_type' => 'pdf',
            'datasheet_file' => $this->pdfFile('nouvelle.pdf'),
        ])->assertOk();

        $product->refresh();
        $this->assertNotSame($oldPath, $product->datasheet_path);
        $this->assertSame('nouvelle.pdf', $product->datasheet_filename);

        // Pas de fichier orphelin en storage
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($product->datasheet_path);
    }

    public function test_switching_from_file_to_text_clears_the_file(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pngFile(),
        ]))->assertCreated();

        $product = Product::first();
        $oldPath = $product->datasheet_path;

        $this->putJson("/api/products/{$product->id}", [
            'datasheet_type' => 'text',
            'datasheet_text' => 'Débit : 10 L/min',
        ])->assertOk();

        $product->refresh();
        $this->assertSame('text', $product->datasheet_type);
        $this->assertNull($product->datasheet_path);
        $this->assertSame('Débit : 10 L/min', $product->datasheet_text);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_datasheet_can_be_removed(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pdfFile(),
        ]))->assertCreated();

        $product = Product::first();
        $oldPath = $product->datasheet_path;

        $this->putJson("/api/products/{$product->id}", ['datasheet_type' => 'none'])->assertOk()
            ->assertJsonPath('data.datasheet', null);

        $product->refresh();
        $this->assertNull($product->datasheet_type);
        $this->assertNull($product->datasheet_path);
        $this->assertNull($product->datasheet_text);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removal_wins_over_a_file_sent_in_the_same_request(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pdfFile(),
        ]))->assertCreated();

        $product = Product::first();

        $this->multipartUpdate("/api/products/{$product->id}", [
            'datasheet_type' => 'none',
            'datasheet_file' => $this->pngFile(),
        ])->assertOk();

        $this->assertNull($product->fresh()->datasheet_type);
    }

    // ═══════════════════════════════════════════════════
    // SUPPRESSION ET ACCÈS
    // ═══════════════════════════════════════════════════

    public function test_deleting_the_product_deletes_its_datasheet_file(): void
    {
        $this->multipart('/api/products', $this->basePayload([
            'datasheet_file' => $this->pdfFile(),
        ]))->assertCreated();

        $product = Product::first();
        $path = $product->datasheet_path;

        $this->deleteJson("/api/products/{$product->id}")->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_product_without_datasheet_exposes_null(): void
    {
        $product = Product::factory()->create(['is_available' => true]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.datasheet', null);
    }

    public function test_datasheet_is_visible_to_visitors(): void
    {
        $this->postJson('/api/products', $this->basePayload([
            'datasheet_type' => 'text',
            'datasheet_text' => 'Garantie : 2 ans',
        ]))->assertCreated();

        $product = Product::first();

        // Déconnexion : la fiche technique est une information publique
        app('auth')->forgetGuards();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.datasheet.text', 'Garantie : 2 ans');
    }

    public function test_a_manager_can_attach_a_datasheet_but_a_customer_cannot(): void
    {
        Role::firstOrCreate(['name' => Role::GESTIONNAIRE], ['label' => 'Gestionnaire']);
        $manager = User::factory()->create();
        $manager->assignRole(Role::GESTIONNAIRE);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/products', $this->basePayload([
                'datasheet_type' => 'text',
                'datasheet_text' => 'Débit : 10 L/min',
            ]))->assertCreated();

        Role::firstOrCreate(['name' => Role::USER], ['label' => 'Client']);
        $customer = User::factory()->create();
        $customer->assignRole(Role::USER);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/products', $this->basePayload(['name' => 'Tentative']))
            ->assertForbidden();
    }
}
