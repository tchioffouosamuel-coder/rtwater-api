<?php

namespace App\Services;

use App\Models\Product;
use App\Support\ImportUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import d'images produit en masse, indépendamment du fichier Excel.
 *
 * C'est le second temps du flux : l'Excel déclare « RTW-B15.jpg » dans sa
 * colonne image, les produits sont créés sans visuel, puis les photos sont
 * téléversées ici et rattachées par leur nom.
 *
 * Même découpage que l'import de catalogue : `analyze()` montre ce qui va être
 * rattaché sans rien écrire, `commit()` applique.
 */
class ProductImageImportService
{
    /**
     * Dépose les images et renvoie l'aperçu des rattachements.
     *
     * @param  UploadedFile[]  $images
     * @return array{token:string, matches:array, unmatched:array, pending:array, summary:array, warnings:array}
     */
    public function analyze(array $images = [], ?UploadedFile $archive = null): array
    {
        ImportUploads::purgeStale();

        $token = ImportUploads::newToken();

        [$count, $warnings] = ImportUploads::putImages($token, $images, $archive);

        if ($count === 0) {
            ImportUploads::forget($token);

            throw new \RuntimeException(
                $warnings[0] ?? 'Aucune image exploitable : formats acceptés JPG, PNG, WebP, GIF, BMP.'
            );
        }

        ImportUploads::writeMeta($token, ['kind' => 'images', 'uploaded' => $count]);

        $preview = $this->buildPreview($token);
        $preview['warnings'] = array_merge($warnings, $preview['warnings']);

        return $preview;
    }

    /**
     * Confronte les fichiers déposés au catalogue.
     *
     * @return array{token:string, matches:array, unmatched:array, pending:array, summary:array, warnings:array}
     */
    public function buildPreview(string $token): array
    {
        ImportUploads::readMeta($token); // lève si le dépôt a expiré

        $index = ImportUploads::indexImages($token);

        $matches   = [];
        $unmatched = [];
        $claimed   = [];

        // Un même fichier ne doit pas être attribué deux fois : le premier
        // produit rencontré le prend, les suivants restent en attente.
        foreach ($this->candidates() as $product) {
            $resolved = $this->resolve($product, $index, $claimed);

            if ($resolved === null) {
                continue;
            }

            $claimed[$resolved['path']] = true;

            $matches[] = [
                'product_id'  => $product->id,
                'name'        => $product->name,
                'sku'         => $product->sku,
                'file'        => basename($resolved['path']),
                'matched_by'  => $resolved['by'],
                'replaces'    => $product->image_url !== null,
            ];
        }

        foreach ($index['files'] as $path) {
            if (!isset($claimed[$path])) {
                $unmatched[] = basename($path);
            }
        }

        // Produits qui attendent encore une image, pour que l'admin sache
        // exactement quels fichiers préparer.
        $matchedIds = array_column($matches, 'product_id');
        $pending = $this->awaitingImages()
            ->whereNotIn('id', $matchedIds ?: [0])
            ->get(['id', 'name', 'sku', 'image_filename'])
            ->map(fn($p) => [
                'product_id' => $p->id,
                'name'       => $p->name,
                'sku'        => $p->sku,
                'expects'    => $p->image_filename,
            ])
            ->all();

        $warnings = [];
        if ($unmatched !== []) {
            $warnings[] = count($unmatched) . ' fichier(s) ne correspondent à aucun produit.';
        }

        return [
            'token'     => $token,
            'matches'   => $matches,
            'unmatched' => $unmatched,
            'pending'   => $pending,
            'summary'   => [
                'uploaded'  => count($index['files']),
                'matched'   => count($matches),
                'replacing' => count(array_filter($matches, fn($m) => $m['replaces'])),
                'unmatched' => count($unmatched),
                'pending'   => count($pending),
            ],
            'warnings'  => $warnings,
        ];
    }

    /**
     * Rattache réellement les images.
     *
     * @param  array{replace_existing?:bool}  $options
     * @return array{attached:int, replaced:int, skipped:int, failed:int, errors:string[]}
     */
    public function commit(string $token, array $options = []): array
    {
        $replaceExisting = (bool) ($options['replace_existing'] ?? true);

        $preview = $this->buildPreview($token);
        $index   = ImportUploads::indexImages($token);
        $disk    = Storage::disk('local');

        $counts = ['attached' => 0, 'replaced' => 0, 'skipped' => 0, 'failed' => 0];
        $errors = [];

        foreach ($preview['matches'] as $match) {
            if ($match['replaces'] && !$replaceExisting) {
                $counts['skipped']++;
                continue;
            }

            $path = ImportUploads::lookup($index, $match['file']);
            if ($path === null || !$disk->exists($path)) {
                $counts['failed']++;
                $errors[] = "Fichier introuvable au moment de l'enregistrement : {$match['file']}.";
                continue;
            }

            try {
                /** @var Product $product */
                $product = Product::findOrFail($match['product_id']);
                $oldImage = $product->image_url;

                $stored = ImportUploads::storeImageBytes(
                    (string) $disk->get($path),
                    pathinfo($path, PATHINFO_EXTENSION),
                    'products',
                );

                if ($stored === null) {
                    $counts['failed']++;
                    $errors[] = "Image illisible : {$match['file']}.";
                    continue;
                }

                $product->update([
                    'image_url' => $stored,
                    // Le nom attendu a été honoré : la colonne ne doit plus
                    // signaler ce produit comme en attente.
                    'image_filename' => null,
                ]);

                // L'ancienne image n'est supprimée qu'une fois la nouvelle en place
                if ($oldImage && $oldImage !== $stored) {
                    Storage::disk('public')->delete($oldImage);
                    $counts['replaced']++;
                } else {
                    $counts['attached']++;
                }
            } catch (\Throwable $e) {
                Log::error('Import images : rattachement en échec', [
                    'product_id' => $match['product_id'],
                    'file'       => $match['file'],
                    'error'      => $e->getMessage(),
                ]);
                $counts['failed']++;
                $errors[] = "{$match['file']} : {$e->getMessage()}";
            }
        }

        ImportUploads::forget($token);

        return $counts + ['errors' => array_slice($errors, 0, 100)];
    }

    public function forget(string $token): void
    {
        ImportUploads::forget($token);
    }

    /** Produits en attente d'image, avec le nom de fichier qu'ils réclament. */
    public function pendingReport(): array
    {
        $awaiting = $this->awaitingImages()->get(['id', 'name', 'sku', 'image_filename']);

        return [
            'data' => $awaiting->map(fn($p) => [
                'product_id' => $p->id,
                'name'       => $p->name,
                'sku'        => $p->sku,
                'expects'    => $p->image_filename,
            ])->all(),
            'summary' => [
                'awaiting'      => $awaiting->count(),
                'without_image' => Product::whereNull('image_url')->count(),
            ],
        ];
    }

    // ═══════════════════════════════════════════════════

    /**
     * Produits qui déclarent un nom de fichier et n'ont pas encore d'image.
     * C'est la définition de « en attente ».
     */
    private function awaitingImages()
    {
        return Product::whereNotNull('image_filename')
            ->whereNull('image_url')
            ->orderBy('name');
    }

    /**
     * Produits candidats au rattachement.
     *
     * Ceux qui attendent explicitement une image d'abord : ils ont priorité sur
     * les autres pour réclamer un fichier, puisqu'ils l'ont nommé.
     */
    private function candidates()
    {
        $awaiting = $this->awaitingImages()->get(['id', 'name', 'sku', 'image_url', 'image_filename']);

        $others = Product::query()
            ->whereNotIn('id', $awaiting->pluck('id')->all() ?: [0])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'image_url', 'image_filename']);

        return $awaiting->concat($others);
    }

    /**
     * Cherche le fichier correspondant à un produit.
     *
     * Trois clés, dans cet ordre de fiabilité : le nom déclaré dans l'Excel,
     * la référence, puis le nom du produit slugifié.
     *
     * @param  array<string, bool>  $claimed
     * @return array{path:string, by:string}|null
     */
    private function resolve(Product $product, array $index, array $claimed): ?array
    {
        $attempts = [
            'filename' => $product->image_filename,
            'sku'      => $product->sku,
            'name'     => $product->name,
        ];

        foreach ($attempts as $by => $hint) {
            if ($hint === null || trim((string) $hint) === '') {
                continue;
            }

            // Le nom déclaré est cherché tel quel ; référence et nom passent
            // par le slug, puisqu'ils ne portent pas d'extension.
            $path = $by === 'filename'
                ? ImportUploads::lookup($index, $hint)
                : ($index['by_stem'][Str::slug($hint)] ?? null);

            if ($path !== null && !isset($claimed[$path])) {
                return ['path' => $path, 'by' => $by];
            }
        }

        return null;
    }
}
