<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ImageOptimizer;
use App\Support\ImportUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import massif de produits depuis un fichier Excel/CSV, avec rattachement
 * automatique des images (archive ZIP, URL distante, ou fichier déjà en storage).
 *
 * Déroulement en deux temps :
 *   1. analyze() — dépose le fichier dans un dossier temporaire, lit et valide
 *      chaque ligne, puis renvoie un aperçu + un jeton. Rien n'est écrit en base.
 *   2. commit($token) — rejoue l'aperçu et enregistre réellement les produits.
 *
 * Le jeton évite de re-téléverser l'archive d'images entre l'aperçu et la validation.
 */
class ProductImportService
{
    /** Dossier des imports en attente, sur le disque privé. */
    private const TMP_DIR = 'imports';

    /** Durée de vie d'un import en attente : au-delà, le dossier est purgé. */
    private const TMP_TTL_HOURS = 24;

    public const MAX_ROWS = 5000;

    private const ARCHIVE_MAX_ENTRIES = 1000;
    private const ARCHIVE_MAX_BYTES   = 300 * 1024 * 1024; // 300 Mo décompressés
    private const IMAGE_MAX_BYTES     = 8 * 1024 * 1024;   // 8 Mo par image
    private const IMAGE_EXTENSIONS    = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    /**
     * En-têtes acceptés par champ. Les libellés sont normalisés (minuscules, sans
     * accent, séparateurs → « _ ») : l'admin peut donc écrire « Prix de vente »,
     * « prix_vente » ou « PRICE » indifféremment.
     */
    private const HEADER_ALIASES = [
        'name'         => ['name', 'nom', 'produit', 'product', 'designation', 'libelle', 'intitule', 'article'],
        'sku'          => ['sku', 'reference', 'ref', 'code', 'code_produit', 'code_article'],
        'description'  => ['description', 'desc', 'details', 'detail', 'resume'],
        'price'        => ['price', 'prix', 'prix_de_vente', 'prix_vente', 'pv', 'prix_ttc', 'tarif'],
        'cost'         => ['cost', 'cout', 'prix_achat', 'prix_d_achat', 'pa', 'cout_achat'],
        'stock'        => ['stock', 'quantite', 'quantite_en_stock', 'qte', 'qty', 'quantity'],
        'min_quantity' => ['min_quantity', 'stock_min', 'stock_minimum', 'seuil', 'seuil_min', 'seuil_alerte', 'quantite_min'],
        'max_quantity' => ['max_quantity', 'stock_max', 'stock_maximum', 'quantite_max'],
        'location'     => ['location', 'emplacement', 'localisation', 'entrepot', 'zone', 'rayon'],
        'category'     => ['category', 'categorie', 'category_name', 'famille', 'type_produit'],
        'image'        => ['image', 'image_url', 'photo', 'visuel', 'illustration', 'picture', 'img', 'fichier_image'],
        'is_available' => ['is_available', 'disponible', 'disponibilite', 'actif', 'active', 'statut', 'status', 'en_ligne', 'publie'],
    ];

    /** Colonnes dont la présence conditionne un import exploitable. */
    private const REQUIRED_FIELDS = ['name', 'price'];

    // ═══════════════════════════════════════════════════
    // ÉTAPE 1 — ANALYSE
    // ═══════════════════════════════════════════════════

    /**
     * Dépose les fichiers téléversés et renvoie l'aperçu de l'import.
     *
     * @param  UploadedFile[]  $images  images isolées (hors archive)
     * @return array{token:string, filename:string, mapping:array, unmapped:array, rows:array, summary:array, images:array, warnings:array}
     */
    public function analyze(UploadedFile $sheet, array $images = [], ?UploadedFile $archive = null): array
    {
        ImportUploads::purgeStale();

        $token = ImportUploads::newToken();

        $ext       = strtolower($sheet->getClientOriginalExtension() ?: 'xlsx');
        $sheetPath = ImportUploads::put(
            $token,
            'sheet.' . $ext,
            (string) file_get_contents($sheet->getRealPath()),
        );

        [$imageCount, $warnings] = ImportUploads::putImages($token, $images, $archive);

        ImportUploads::writeMeta($token, [
            'filename'   => $sheet->getClientOriginalName(),
            'sheet_path' => $sheetPath,
        ]);

        $preview = $this->buildPreview($token);
        $preview['warnings'] = array_merge($warnings, $preview['warnings']);
        $preview['images']['uploaded'] = $imageCount;

        return $preview;
    }

    /**
     * Relit le fichier déposé et produit l'aperçu ligne par ligne.
     *
     * @return array{token:string, filename:string, mapping:array, unmapped:array, rows:array, summary:array, images:array, warnings:array}
     */
    public function buildPreview(string $token): array
    {
        $meta = ImportUploads::readMeta($token);
        if (!isset($meta['sheet_path'], $meta['filename'])) {
            throw new \RuntimeException("Import corrompu : relancez l'analyse du fichier.");
        }

        $sheetPath = Storage::disk('local')->path($meta['sheet_path']);

        $raw = $this->readRows($sheetPath);
        if ($raw === []) {
            throw new \RuntimeException('Le fichier ne contient aucune donnée exploitable.');
        }

        [$mapping, $unmapped] = $this->mapHeaders(array_shift($raw));

        $missing = array_values(array_diff(self::REQUIRED_FIELDS, array_keys($mapping)));
        if ($missing !== []) {
            throw new \RuntimeException(
                'Colonnes obligatoires absentes : ' . implode(', ', $missing)
                . ". Téléchargez le modèle pour voir les en-têtes attendus."
            );
        }

        $imageIndex = ImportUploads::indexImages($token);
        $existing   = $this->existingProductsIndex();
        $categories = $this->categoriesIndex();

        $rows      = [];
        $warnings  = [];
        $seenSkus  = [];
        $seenNames = [];
        $matched   = 0;
        $truncated = false;

        foreach ($raw as $offset => $cells) {
            $line = $offset + 2; // 1-indexé, ligne d'en-tête comprise

            if ($this->isBlankRow($cells)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }

            $data   = $this->extractRow($cells, $mapping);
            $errors = $this->validateRow($data);

            // Doublons à l'intérieur du fichier lui-même
            $skuKey = $data['sku'] !== null ? mb_strtolower($data['sku']) : null;
            if ($skuKey !== null) {
                if (isset($seenSkus[$skuKey])) {
                    $errors[] = "Référence « {$data['sku']} » déjà présente ligne {$seenSkus[$skuKey]} du fichier.";
                } else {
                    $seenSkus[$skuKey] = $line;
                }
            }

            $nameKey = mb_strtolower(trim((string) $data['name']));
            if ($skuKey === null && $nameKey !== '') {
                if (isset($seenNames[$nameKey])) {
                    $errors[] = "Produit « {$data['name']} » déjà présent ligne {$seenNames[$nameKey]} du fichier.";
                } else {
                    $seenNames[$nameKey] = $line;
                }
            }

            // Catégorie : connue, ou à créer
            $categoryState = $this->resolveCategoryState($data['category'], $categories);

            // Image : résolution automatique
            $image = $this->planImage($data, $imageIndex);
            if ($image['source'] !== 'none') {
                $matched++;
            }
            if ($image['warning'] !== null) {
                $warnings[] = "Ligne $line : {$image['warning']}";
            }

            $match = $this->findExisting($data, $existing);

            $rows[] = [
                'line'     => $line,
                'action'   => $errors !== [] ? 'error' : ($match ? 'update' : 'create'),
                'product'  => $data,
                'existing' => $match ? ['id' => $match['id'], 'name' => $match['name'], 'stock' => $match['stock']] : null,
                'category' => $categoryState,
                'image'    => [
                    'source'  => $image['source'],
                    'ref'     => $image['ref'] ? basename($image['ref']) : null,
                    // Nom declare dans l'Excel mais pas encore televerse
                    'expects' => $image['expects'],
                ],
                'errors'   => $errors,
            ];
        }

        if ($truncated) {
            $warnings[] = 'Fichier tronqué : seules les ' . self::MAX_ROWS . ' premières lignes sont traitées.';
        }
        if ($unmapped !== []) {
            $warnings[] = 'Colonnes ignorées (en-tête inconnu) : ' . implode(', ', $unmapped) . '.';
        }

        $pendingImages = count(array_filter($rows, fn($r) => $r['image']['expects'] !== null));
        if ($pendingImages > 0) {
            $warnings[] = "$pendingImages produit(s) attendent une image : leur nom de fichier est "
                . "enregistré, téléversez les photos depuis « Import d'images ».";
        }

        $unusedImages = max(0, count($imageIndex['files']) - $matched);
        if ($unusedImages > 0) {
            $warnings[] = "$unusedImages image(s) téléversée(s) n'ont trouvé aucun produit correspondant.";
        }

        $newCategories = array_unique(array_filter(array_map(
            fn($r) => $r['category']['status'] === 'new' ? $r['category']['name'] : null,
            $rows
        )));

        return [
            'token'    => $token,
            'filename' => $meta['filename'],
            'mapping'  => $mapping,
            'unmapped' => $unmapped,
            'rows'     => $rows,
            'summary'  => [
                'total'          => count($rows),
                'create'         => count(array_filter($rows, fn($r) => $r['action'] === 'create')),
                'update'         => count(array_filter($rows, fn($r) => $r['action'] === 'update')),
                'error'          => count(array_filter($rows, fn($r) => $r['action'] === 'error')),
                'images'         => $matched,
                'images_pending'  => $pendingImages,
                'new_categories' => array_values($newCategories),
            ],
            'images'   => [
                'available' => count($imageIndex['files']),
                'matched'   => $matched,
                'unused'    => $unusedImages,
            ],
            'warnings' => $warnings,
        ];
    }

    // ═══════════════════════════════════════════════════
    // ÉTAPE 2 — ENREGISTREMENT
    // ═══════════════════════════════════════════════════

    /**
     * Enregistre l'import analysé. Chaque ligne est traitée dans sa propre
     * transaction : une ligne en échec n'annule pas les précédentes.
     *
     * @param  array{update_existing?:bool, create_categories?:bool, allow_remote_images?:bool}  $options
     */
    public function commit(string $token, array $options = [], ?User $user = null): ProductImport
    {
        $options = [
            'update_existing'     => (bool) ($options['update_existing'] ?? true),
            'create_categories'   => (bool) ($options['create_categories'] ?? true),
            'allow_remote_images' => (bool) ($options['allow_remote_images'] ?? true),
        ];

        $preview    = $this->buildPreview($token);
        $imageIndex = ImportUploads::indexImages($token);

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'images' => 0];
        $errors = [];

        foreach ($preview['rows'] as $row) {
            if ($row['action'] === 'error') {
                $counts['failed']++;
                $errors[] = ['line' => $row['line'], 'name' => $row['product']['name'], 'messages' => $row['errors']];
                continue;
            }

            if ($row['action'] === 'update' && !$options['update_existing']) {
                $counts['skipped']++;
                continue;
            }

            try {
                // L'image est écrite hors transaction : un rollback ne défait pas un fichier
                $imagePath = null;
                if ($row['image']['source'] !== 'none') {
                    $imagePath = $this->materializeImage($row, $imageIndex, $options['allow_remote_images']);
                    if ($imagePath !== null) {
                        $counts['images']++;
                    }
                }

                DB::transaction(function () use ($row, $options, $imagePath, $user, &$counts) {
                    $categoryId = $this->resolveCategoryId($row['category'], $options['create_categories']);
                    $attributes = $this->attributesFor($row['product'], $categoryId, $imagePath);

                    // Nom de fichier attendu : cle de rattachement pour l'import
                    // d'images ulterieur. Efface des que l'image est en place.
                    if ($row['image']['expects'] !== null) {
                        $attributes['image_filename'] = $row['image']['expects'];
                    } elseif ($imagePath !== null) {
                        $attributes['image_filename'] = null;
                    }

                    if ($row['existing']) {
                        /** @var Product $product */
                        $product  = Product::findOrFail($row['existing']['id']);
                        $before   = (int) $product->stock;
                        $oldImage = $product->image_url;

                        $product->update($attributes);

                        // L'ancienne image n'est supprimée qu'une fois la nouvelle en place
                        if ($imagePath !== null && $oldImage && $oldImage !== $imagePath) {
                            Storage::disk('public')->delete($oldImage);
                        }

                        $this->logStock($product, $before, $user, 'adjustment');
                        $counts['updated']++;
                    } else {
                        $product = Product::create($attributes);
                        $this->logStock($product, 0, $user, 'in');
                        $counts['created']++;
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Import produit : ligne en échec', [
                    'line'  => $row['line'],
                    'error' => $e->getMessage(),
                ]);
                $counts['failed']++;
                $errors[] = [
                    'line'     => $row['line'],
                    'name'     => $row['product']['name'],
                    'messages' => ["Erreur d'enregistrement : {$e->getMessage()}"],
                ];
            }
        }

        $import = ProductImport::create([
            'user_id'       => $user?->id,
            'filename'      => $preview['filename'],
            'rows_total'    => count($preview['rows']),
            'created_count' => $counts['created'],
            'updated_count' => $counts['updated'],
            'skipped_count' => $counts['skipped'],
            'failed_count'  => $counts['failed'],
            'images_count'  => $counts['images'],
            'options'       => $options,
            'errors'        => array_slice($errors, 0, 200),
        ]);

        $this->forget($token);

        return $import;
    }

    /** Supprime le dossier temporaire d'un import abandonné. */
    public function forget(string $token): void
    {
        ImportUploads::forget($token);
    }

    // ═══════════════════════════════════════════════════
    // MODÈLE XLSX
    // ═══════════════════════════════════════════════════

    /** Génère le fichier modèle à remplir par l'admin. Retourne le binaire XLSX. */
    public function template(): string
    {
        $columns = [
            ['nom', 'Nom du produit', 'Obligatoire. Ex : Bouteille d\'eau 1,5 L'],
            ['reference', 'Référence (SKU)', 'Recommandé : clé de mise à jour et de rattachement de l\'image'],
            ['categorie', 'Catégorie', 'Nom ou slug. Créée automatiquement si absente'],
            ['description', 'Description', 'Texte libre'],
            ['prix', 'Prix de vente', 'Obligatoire. Nombre en XAF, ex : 1500'],
            ['prix_achat', 'Prix d\'achat', 'Optionnel'],
            ['stock', 'Stock', 'Entier. Vide = inchangé'],
            ['stock_min', 'Seuil d\'alerte', 'Optionnel'],
            ['stock_max', 'Stock maximum', 'Optionnel'],
            ['emplacement', 'Emplacement', 'Ex : Entrepôt A — Rayon 3'],
            ['image', 'Image', 'Nom du fichier photo (ex : RTW-B15.jpg), ou URL https. Les photos peuvent etre televersees plus tard'],
            ['disponible', 'Disponible', 'oui / non'],
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');

        foreach ($columns as $i => [$key, $label, $help]) {
            $col = $i + 1;
            $sheet->setCellValue([$col, 1], $key);
            $sheet->setCellValue([$col, 2], $label);
            $sheet->getComment([$col, 1])->getText()->createText($help);
            $sheet->getColumnDimensionByColumn($col)->setWidth(max(14, min(42, mb_strlen($label) + 10)));
        }

        $lastCol = count($columns);

        // Ligne 1 : en-têtes techniques (seuls lus par l'import). Ligne 2 : libellés d'aide.
        $sheet->getStyle([1, 1, $lastCol, 1])->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle([1, 2, $lastCol, 2])->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $examples = [
            ['Bouteille d\'eau 1,5 L', 'RTW-B15', 'Bouteilles', 'Eau minérale naturelle, pack de 6', 3500, 2400, 120, 20, 500, 'Entrepôt A', 'RTW-B15.jpg', 'oui'],
            ['Bidon d\'eau 10 L', 'RTW-D10', 'Bidons', 'Bidon réutilisable 10 litres', 6000, 4200, 60, 10, 200, 'Entrepôt A', 'RTW-D10.jpg', 'oui'],
            ['Fontaine à eau de bureau', 'RTW-FT01', 'Équipements', 'Fontaine chaude / froide', 185000, 140000, 8, 2, 20, 'Showroom', 'https://exemple.com/fontaine.jpg', 'non'],
        ];
        foreach ($examples as $r => $values) {
            foreach ($values as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 3], $value);
            }
        }
        $sheet->getStyle([1, 3, $lastCol, 2 + count($examples)])->applyFromArray([
            'font'    => ['color' => ['rgb' => '94A3B8']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);

        $sheet->freezePane('A3');

        // Onglet d'aide : mode d'emploi + catégories existantes
        $help = $spreadsheet->createSheet();
        $help->setTitle('Aide');
        $help->setCellValue('A1', 'Comment remplir ce fichier');
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $lines = [
            '',
            '1. Les lignes 3 à 5 sont des exemples : supprimez-les avant l\'import. Les lignes 1 et 2 doivent rester.',
            '2. Une ligne = un produit. La colonne « reference » (SKU) sert de clé : si elle correspond à un produit',
            '   existant, celui-ci est mis à jour au lieu d\'être recréé. Sans référence, la correspondance se fait sur le nom.',
            '3. Une colonne laissée vide ne remplace pas la valeur déjà enregistrée : utile pour ne mettre à jour que les prix.',
            '4. Images — indiquez simplement le NOM du fichier photo dans la colonne « image »,',
            '   par exemple RTW-B15.jpg. Vous n\'avez pas besoin d\'avoir les photos sous la main :',
            '   le nom est enregistré, et vous téléversez toutes les photos en une seule fois plus tard,',
            '   depuis Produits > Import Excel > onglet « Images ». Le rattachement est automatique.',
            '   Deux autres possibilités :',
            '   • colonne « image » = URL https://... → l\'image est téléchargée tout de suite ;',
            '   • colonne « image » vide → une photo nommée comme la référence (RTW-B15.jpg) ou comme',
            '     le produit (bouteille-d-eau-1-5-l.png) sera rattachée. Suffixes -1 / _1 acceptés.',
            '5. Toutes les images sont redimensionnées (1600 px) et converties en WebP automatiquement.',
            '6. Les prix acceptent les espaces et séparateurs : « 1 500 », « 1500,50 », « 1,500.50 ».',
            '7. Formats de fichier acceptés : .xlsx, .xls, .csv.',
            '',
            'Catégories produit existantes :',
        ];
        foreach ($lines as $i => $text) {
            $help->setCellValue([1, $i + 2], $text);
        }
        $row = count($lines) + 2;
        foreach (Category::where('type', 'product')->orderBy('name')->pluck('name') as $name) {
            $help->setCellValue([1, $row++], '   • ' . $name);
        }
        $help->getColumnDimension('A')->setWidth(110);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $binary;
    }

    // ═══════════════════════════════════════════════════
    // LECTURE DU FICHIER
    // ═══════════════════════════════════════════════════

    /** @return array<int, array<int, mixed>> */
    private function readRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        // Lignes vides en tête de fichier (mise en forme, titre fusionné...)
        while ($rows !== [] && $this->isBlankRow($rows[0])) {
            array_shift($rows);
        }

        return array_values($rows);
    }

    /**
     * Associe les en-têtes du fichier aux champs connus, en deux passes :
     * d'abord les correspondances exactes, puis une passe tolérante qui
     * reconnaît « Nom du produit » ou « Prix de vente HT » à partir des mots
     * qu'ils contiennent. La passe exacte d'abord, pour qu'une colonne
     * « prix_achat » ne soit jamais prise pour la colonne « prix ».
     *
     * @param  array<int, mixed>  $header
     * @return array{0: array<string,int>, 1: string[]}
     */
    private function mapHeaders(array $header): array
    {
        $lookup = [];
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$alias] = $field;
            }
        }

        // Alias les plus longs en premier : « prix_achat » avant « prix »
        $aliasesByLength = array_keys($lookup);
        usort($aliasesByLength, fn($a, $b) => strlen($b) <=> strlen($a));

        $normalized = [];
        foreach ($header as $index => $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $normalized[$index] = ['label' => $label, 'key' => $this->normalizeHeader($label)];
            }
        }

        $mapping = [];

        // Passe 1 — libellé exactement connu
        foreach ($normalized as $index => $column) {
            $field = $lookup[$column['key']] ?? null;
            if ($field !== null && !isset($mapping[$field])) {
                $mapping[$field] = $index;
                unset($normalized[$index]);
            }
        }

        // Passe 2 — le libellé contient un alias connu comme mot entier
        foreach ($normalized as $index => $column) {
            foreach ($aliasesByLength as $alias) {
                if (strlen($alias) < 3 || isset($mapping[$lookup[$alias]])) {
                    continue;
                }
                if (preg_match('/(^|_)' . preg_quote($alias, '/') . '($|_)/', $column['key'])) {
                    $mapping[$lookup[$alias]] = $index;
                    unset($normalized[$index]);
                    break;
                }
            }
        }

        asort($mapping); // champs rendus dans l'ordre des colonnes du fichier

        return [$mapping, array_values(array_column($normalized, 'label'))];
    }

    private function normalizeHeader(string $label): string
    {
        $ascii = preg_replace('/[^a-zA-Z0-9]+/', '_', Str::ascii($label)) ?? '';

        return trim(mb_strtolower($ascii), '_');
    }

    /** @param array<int, mixed> $cells */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string,int>  $mapping
     * @return array<string, mixed>
     */
    private function extractRow(array $cells, array $mapping): array
    {
        $get = function (string $field) use ($cells, $mapping): ?string {
            if (!isset($mapping[$field])) {
                return null;
            }
            $value = trim((string) ($cells[$mapping[$field]] ?? ''));

            return $value === '' ? null : $value;
        };

        return [
            'name'         => $get('name'),
            'sku'          => $get('sku'),
            'description'  => $get('description'),
            'price'        => $this->toNumber($get('price')),
            'cost'         => $this->toNumber($get('cost')),
            'stock'        => $this->toInt($get('stock')),
            'min_quantity' => $this->toInt($get('min_quantity')),
            'max_quantity' => $this->toInt($get('max_quantity')),
            'location'     => $get('location'),
            'category'     => $get('category'),
            'image'        => $get('image'),
            'is_available' => $this->toBool($get('is_available')),
        ];
    }

    /** « 1 500,50 », « 1,500.50 », « 1500 XAF » → 1500.5 */
    private function toNumber(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9,.\-]/u', '', $value) ?? '';
        if ($clean === '' || $clean === '-') {
            return null;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot   = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Le séparateur décimal est le dernier des deux ; l'autre groupe les milliers
            $decimal  = $lastComma > $lastDot ? ',' : '.';
            $thousand = $decimal === ',' ? '.' : ',';
            $clean    = str_replace($decimal, '.', str_replace($thousand, '', $clean));
        } elseif ($lastComma !== false) {
            // « 1,500 » = séparateur de milliers ; « 1,5 » = décimale
            $clean = (strlen($clean) - $lastComma - 1) === 3
                ? str_replace(',', '', $clean)
                : str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function toInt(?string $value): ?int
    {
        $number = $this->toNumber($value);

        return $number === null ? null : (int) round($number);
    }

    private function toBool(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $key = mb_strtolower(trim(Str::ascii($value)));

        return match (true) {
            in_array($key, ['1', 'oui', 'o', 'yes', 'y', 'true', 'vrai', 'actif', 'active', 'disponible', 'en stock', 'publie', 'en ligne'], true) => true,
            in_array($key, ['0', 'non', 'n', 'no', 'false', 'faux', 'inactif', 'indisponible', 'brouillon', 'hors ligne', 'epuise'], true) => false,
            default => null,
        };
    }

    // ═══════════════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════════════

    /**
     * @param  array<string, mixed>  $data
     * @return string[]
     */
    private function validateRow(array $data): array
    {
        $validator = Validator::make($data, [
            'name'         => 'required|string|max:255',
            'sku'          => 'nullable|string|max:100',
            'description'  => 'nullable|string|max:20000',
            'price'        => 'required|numeric|min:0|max:99999999',
            'cost'         => 'nullable|numeric|min:0|max:99999999',
            'stock'        => 'nullable|integer|min:0|max:1000000',
            'min_quantity' => 'nullable|integer|min:0|max:1000000',
            'max_quantity' => 'nullable|integer|min:0|max:1000000',
            'location'     => 'nullable|string|max:255',
            'category'     => 'nullable|string|max:255',
        ], [
            'name.required'  => 'Le nom du produit est obligatoire.',
            'name.max'       => 'Le nom dépasse 255 caractères.',
            'price.required' => 'Le prix est obligatoire (ou illisible).',
            'price.numeric'  => 'Le prix doit être un nombre.',
            'price.min'      => 'Le prix ne peut pas être négatif.',
            'cost.numeric'   => 'Le prix d\'achat doit être un nombre.',
            'stock.integer'  => 'Le stock doit être un nombre entier.',
            'stock.min'      => 'Le stock ne peut pas être négatif.',
        ]);

        $errors = $validator->fails()
            ? array_merge(...array_values($validator->errors()->toArray()))
            : [];

        if ($data['min_quantity'] !== null && $data['max_quantity'] !== null
            && $data['max_quantity'] > 0 && $data['min_quantity'] > $data['max_quantity']) {
            $errors[] = 'Le seuil minimum dépasse le stock maximum.';
        }

        return array_values($errors);
    }

    // ═══════════════════════════════════════════════════
    // PRODUITS & CATÉGORIES EXISTANTS
    // ═══════════════════════════════════════════════════

    /** @return array{by_sku: array<string, array>, by_name: array<string, array>} */
    private function existingProductsIndex(): array
    {
        $index = ['by_sku' => [], 'by_name' => []];

        Product::select('id', 'name', 'sku', 'stock')->chunkById(500, function ($products) use (&$index) {
            foreach ($products as $product) {
                $entry = ['id' => $product->id, 'name' => $product->name, 'stock' => (int) $product->stock];
                if ($product->sku) {
                    $index['by_sku'][mb_strtolower($product->sku)] ??= $entry;
                }
                $index['by_name'][mb_strtolower(trim($product->name))] ??= $entry;
            }
        });

        return $index;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id:int, name:string, stock:int}|null
     */
    private function findExisting(array $data, array $index): ?array
    {
        if ($data['sku'] !== null) {
            // Avec une référence, elle seule fait foi : deux produits peuvent porter le même nom
            return $index['by_sku'][mb_strtolower($data['sku'])] ?? null;
        }

        $name = mb_strtolower(trim((string) $data['name']));

        return $name === '' ? null : ($index['by_name'][$name] ?? null);
    }

    /** @return array<string, array{id:int, name:string}> */
    private function categoriesIndex(): array
    {
        $index = [];
        foreach (Category::where('type', 'product')->get(['id', 'name', 'slug']) as $category) {
            $entry = ['id' => $category->id, 'name' => $category->name];
            $index[mb_strtolower(trim($category->name))] ??= $entry;
            $index[mb_strtolower($category->slug)] ??= $entry;
        }

        return $index;
    }

    /** @return array{status:string, name:?string, id:?int} */
    private function resolveCategoryState(?string $label, array $index): array
    {
        if ($label === null) {
            return ['status' => 'none', 'name' => null, 'id' => null];
        }

        $match = $index[mb_strtolower(trim($label))] ?? $index[Str::slug($label)] ?? null;

        return $match
            ? ['status' => 'known', 'name' => $match['name'], 'id' => $match['id']]
            : ['status' => 'new', 'name' => trim($label), 'id' => null];
    }

    private function resolveCategoryId(array $state, bool $createMissing): ?int
    {
        if ($state['status'] === 'none' || ($state['status'] === 'new' && !$createMissing)) {
            return null;
        }
        if ($state['status'] === 'known') {
            return $state['id'];
        }

        $name = $state['name'];
        $slug = Str::slug($name) ?: Str::random(8);

        // firstOrCreate sur le slug : deux lignes citant la même catégorie n'en créent qu'une
        return Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'type' => 'product']
        )->id;
    }

    /** @return array<string, mixed> */
    private function attributesFor(array $data, ?int $categoryId, ?string $imagePath): array
    {
        $attributes = [
            'name'  => $data['name'],
            'price' => $data['price'],
        ];

        // Une colonne absente ou vide ne doit pas écraser la valeur déjà enregistrée
        foreach (['sku', 'description', 'cost', 'location', 'stock', 'min_quantity', 'max_quantity'] as $field) {
            if ($data[$field] !== null) {
                $attributes[$field] = $data[$field];
            }
        }
        if ($data['is_available'] !== null) {
            $attributes['is_available'] = $data['is_available'];
        }
        if ($categoryId !== null) {
            $attributes['category_id'] = $categoryId;
        }
        if ($imagePath !== null) {
            $attributes['image_url'] = $imagePath;
        }

        return $attributes;
    }

    /** Rattache l'import au journal d'inventaire existant. */
    private function logStock(Product $product, int $before, ?User $user, string $type): void
    {
        $after = (int) $product->stock;
        if ($after === $before) {
            return;
        }

        StockMovement::create([
            'product_id'   => $product->id,
            'user_id'      => $user?->id,
            'type'         => $type,
            'quantity'     => $after - $before,
            'stock_before' => $before,
            'stock_after'  => $after,
            'reason'       => 'import',
            'notes'        => 'Import massif Excel',
        ]);
    }

    // ═══════════════════════════════════════════════════
    // IMAGES
    // ═══════════════════════════════════════════════════

    /**
     * Détermine l'image à rattacher à une ligne, sans rien écrire ni télécharger.
     *
     * @return array{source:string, ref:?string, expects:?string, warning:?string}
     */
    private function planImage(array $data, array $index): array
    {
        $value = $data['image'];

        if ($value !== null) {
            if (preg_match('#^https?://#i', $value)) {
                return ['source' => 'url', 'ref' => $value, 'expects' => null, 'warning' => null];
            }

            // Chemin de storage (« products/xxx.webp ») : réimport du même fichier.
            // Le slash distingue un chemin d'un simple nom de fichier, qui doit
            // d'abord être cherché dans l'archive téléversée.
            if (str_contains($value, '/') && Storage::disk('public')->exists($value)) {
                return ['source' => 'existing', 'ref' => $value, 'expects' => null, 'warning' => null];
            }

            $candidate = basename(str_replace('\\', '/', $value));
            $path = ImportUploads::lookup($index, $candidate);

            if ($path !== null) {
                return ['source' => 'archive', 'ref' => $path, 'expects' => null, 'warning' => null];
            }

            // Fichier pas encore disponible : le nom attendu est enregistré sur le
            // produit. L'import d'images en masse s'en servira pour rattacher le
            // fichier plus tard — c'est le flux « Excel d'abord, photos ensuite ».
            return [
                'source'  => 'none',
                'ref'     => null,
                'expects' => $candidate,
                'warning' => null,
            ];
        }

        // Référencement automatique : d'abord la référence, puis le nom du produit
        foreach ([$data['sku'], $data['name']] as $hint) {
            if ($hint === null) {
                continue;
            }
            $stem = Str::slug($hint);
            if ($stem !== '' && isset($index['by_stem'][$stem])) {
                return ['source' => 'archive', 'ref' => $index['by_stem'][$stem], 'expects' => null, 'warning' => null];
            }
        }

        return ['source' => 'none', 'ref' => null, 'expects' => null, 'warning' => null];
    }

    /**
     * Écrit réellement l'image sur le disque public. Retourne le chemin relatif,
     * ou null si l'image n'a pas pu être récupérée (le produit est alors importé sans image).
     */
    private function materializeImage(array $row, array $index, bool $allowRemote): ?string
    {
        $source = $row['image']['source'];
        $value  = $row['product']['image'];

        if ($source === 'existing') {
            return $value;
        }

        if ($source === 'url') {
            return $allowRemote ? $this->downloadImage((string) $value) : null;
        }

        if ($source !== 'archive') {
            return null;
        }

        // On rejoue la résolution : l'aperçu ne transporte que le nom de fichier
        $plan = $this->planImage($row['product'], $index);
        if ($plan['source'] !== 'archive') {
            return null;
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($plan['ref'])) {
            return null;
        }

        return $this->storeImageBytes(
            (string) $disk->get($plan['ref']),
            pathinfo($plan['ref'], PATHINFO_EXTENSION)
        );
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('Import produit : image distante inaccessible', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            if (!str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/')) {
                Log::warning('Import produit : l\'URL ne renvoie pas une image', ['url' => $url]);
                return null;
            }

            $binary = $response->body();
            if ($binary === '' || strlen($binary) > self::IMAGE_MAX_BYTES) {
                return null;
            }

            return $this->storeImageBytes(
                $binary,
                strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION))
            );
        } catch (\Throwable $e) {
            Log::warning('Import produit : téléchargement image échoué', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /** Convertit en WebP si GD le permet, sinon stocke le fichier tel quel. */
    private function storeImageBytes(string $binary, string $extension): ?string
    {
        return ImportUploads::storeImageBytes($binary, $extension, 'products');
    }

    // ═══════════════════════════════════════════════════
    // ARCHIVE ZIP & DOSSIER TEMPORAIRE
    // ═══════════════════════════════════════════════════

}
