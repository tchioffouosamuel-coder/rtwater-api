<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dépôt temporaire des fichiers d'un import, et indexation des images.
 *
 * Partagé entre l'import de catalogue (Excel) et l'import d'images en masse :
 * les deux déposent des fichiers sous un jeton, extraient une archive ZIP et
 * cherchent une image par son nom. Dupliquer l'extraction d'archive aurait
 * surtout dupliqué ses gardes de sécurité — avec le risque qu'une seule des
 * deux copies soit corrigée un jour.
 */
class ImportUploads
{
    /** Dossier des imports en attente, sur le disque privé. */
    public const TMP_DIR = 'imports';

    /** Durée de vie d'un import en attente : au-delà, le dossier est purgé. */
    public const TMP_TTL_HOURS = 24;

    public const ARCHIVE_MAX_ENTRIES = 1000;
    public const ARCHIVE_MAX_BYTES   = 300 * 1024 * 1024; // 300 Mo décompressés
    public const IMAGE_MAX_BYTES     = 8 * 1024 * 1024;   // 8 Mo par image
    public const IMAGE_EXTENSIONS    = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    /** Jeton opaque, seul identifiant d'un dépôt en cours. */
    public static function newToken(): string
    {
        return Str::random(32);
    }

    /** Le jeton vient du client : il ne doit jamais pouvoir sortir du dossier d'imports. */
    public static function assertToken(string $token): void
    {
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            throw new \RuntimeException("Jeton d'import invalide.");
        }
    }

    public static function directory(string $token): string
    {
        self::assertToken($token);

        return self::TMP_DIR . '/' . $token;
    }

    public static function imagesDirectory(string $token): string
    {
        return self::directory($token) . '/images';
    }

    /**
     * Dépose les images d'un import : fichiers isolés et/ou archive ZIP.
     *
     * @param  UploadedFile[]  $images
     * @return array{0:int, 1:string[]}  nombre de fichiers retenus, avertissements
     */
    public static function putImages(string $token, array $images = [], ?UploadedFile $archive = null): array
    {
        $disk = Storage::disk('local');
        $base = self::imagesDirectory($token);

        $warnings = [];
        $count    = 0;

        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $name = self::safeImageName($image->getClientOriginalName());
            if ($name === null) {
                $warnings[] = "Fichier ignoré (format non supporté) : {$image->getClientOriginalName()}";
                continue;
            }

            $disk->put("$base/$name", (string) file_get_contents($image->getRealPath()));
            $count++;
        }

        if ($archive) {
            [$extracted, $archiveWarnings] = self::extractArchive($archive, $disk->path($base));
            $count += $extracted;
            $warnings = array_merge($warnings, $archiveWarnings);
        }

        return [$count, $warnings];
    }

    /** Écrit un fichier quelconque sous le jeton. Retourne son chemin relatif. */
    public static function put(string $token, string $relativePath, string $contents): string
    {
        $path = self::directory($token) . '/' . ltrim($relativePath, '/');
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    /** @param array<string, mixed> $meta */
    public static function writeMeta(string $token, array $meta): void
    {
        self::put($token, 'meta.json', (string) json_encode(
            $meta + ['created_at' => now()->toIso8601String()],
            JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @return array<string, mixed> */
    public static function readMeta(string $token): array
    {
        $disk = Storage::disk('local');
        $path = self::directory($token) . '/meta.json';

        if (!$disk->exists($path)) {
            throw new \RuntimeException("Import expiré ou introuvable : relancez l'analyse.");
        }

        $meta = json_decode((string) $disk->get($path), true);
        if (!is_array($meta)) {
            throw new \RuntimeException("Import corrompu : relancez l'analyse.");
        }

        return $meta;
    }

    public static function forget(string $token): void
    {
        Storage::disk('local')->deleteDirectory(self::directory($token));
    }

    /**
     * Indexe les images déposées sous ce jeton.
     *
     * Trois clés par fichier : le nom complet (« rtw-b15.jpg »), le nom sans
     * extension normalisé (« rtw-b15 »), et ce même nom privé d'un suffixe
     * numérique (« rtw-b15-1 » → « rtw-b15 ») pour les séries de photos.
     *
     * @return array{files: string[], by_name: array<string,string>, by_stem: array<string,string>}
     */
    public static function indexImages(string $token): array
    {
        $files = Storage::disk('local')->files(self::imagesDirectory($token));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $index = ['files' => [], 'by_name' => [], 'by_stem' => []];

        foreach ($files as $path) {
            $basename = basename($path);
            if (!in_array(strtolower(pathinfo($basename, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true)) {
                continue;
            }

            $index['files'][] = $path;
            $index['by_name'][mb_strtolower($basename)] ??= $path;

            $stem = Str::slug(pathinfo($basename, PATHINFO_FILENAME));
            if ($stem === '') {
                continue;
            }
            $index['by_stem'][$stem] ??= $path;

            // « photo-1.jpg » répond aussi à « photo » : premier fichier de la série
            $base = preg_replace('/[-_]\d+$/', '', $stem) ?? $stem;
            if ($base !== '' && $base !== $stem) {
                $index['by_stem'][$base] ??= $path;
            }
        }

        return $index;
    }

    /**
     * Cherche une image dans l'index, par nom de fichier puis par nom sans extension.
     *
     * @param  array{by_name: array<string,string>, by_stem: array<string,string>}  $index
     */
    public static function lookup(array $index, ?string $candidate): ?string
    {
        if ($candidate === null || trim($candidate) === '') {
            return null;
        }

        $basename = basename(str_replace('\\', '/', trim($candidate)));

        return $index['by_name'][mb_strtolower($basename)]
            ?? $index['by_stem'][Str::slug(pathinfo($basename, PATHINFO_FILENAME))]
            ?? null;
    }

    /**
     * Extrait les images d'une archive ZIP. Les chemins sont aplatis et filtrés :
     * seul le nom de fichier est conservé, jamais le sous-chemin de l'archive.
     *
     * @return array{0:int, 1:string[]}
     */
    public static function extractArchive(UploadedFile $archive, string $destination): array
    {
        $warnings = [];

        if (!class_exists(\ZipArchive::class)) {
            return [0, ["L'extension PHP zip est absente du serveur : l'archive n'a pas pu être lue."]];
        }

        $zip = new \ZipArchive();
        if ($zip->open($archive->getRealPath()) !== true) {
            return [0, ["Archive ZIP illisible : {$archive->getClientOriginalName()}."]];
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $count = 0;
        $bytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($count >= self::ARCHIVE_MAX_ENTRIES) {
                $warnings[] = 'Archive tronquée : ' . self::ARCHIVE_MAX_ENTRIES . ' images maximum par import.';
                break;
            }

            $stat = $zip->statIndex($i);
            if ($stat === false || str_ends_with($stat['name'], '/')) {
                continue;
            }

            // Dossiers techniques ajoutés par macOS / certains utilitaires
            if (str_contains($stat['name'], '__MACOSX') || str_starts_with(basename($stat['name']), '.')) {
                continue;
            }

            $name = self::safeImageName(basename(str_replace('\\', '/', $stat['name'])));
            if ($name === null) {
                continue;
            }

            if ($stat['size'] > self::IMAGE_MAX_BYTES) {
                $warnings[] = "Image ignorée (plus de 8 Mo) : $name.";
                continue;
            }
            if ($bytes + $stat['size'] > self::ARCHIVE_MAX_BYTES) {
                $warnings[] = 'Archive tronquée : volume décompressé maximum atteint.';
                break;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            file_put_contents(
                $destination . DIRECTORY_SEPARATOR . self::uniqueName($destination, $name),
                $contents
            );

            $bytes += $stat['size'];
            $count++;
        }

        $zip->close();

        if ($count === 0 && $warnings === []) {
            $warnings[] = "Aucune image exploitable dans l'archive {$archive->getClientOriginalName()}.";
        }

        return [$count, $warnings];
    }

    /** Nom de fichier image sûr, ou null si l'extension n'est pas celle d'une image. */
    public static function safeImageName(string $filename): ?string
    {
        $filename  = basename(str_replace('\\', '/', $filename));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return null;
        }

        // Le nom reste lisible — il sert au rattachement automatique — mais assaini
        $stem = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '-', pathinfo($filename, PATHINFO_FILENAME)) ?? '';
        $stem = trim(str_replace('..', '.', $stem), '-. ');

        return $stem === '' ? null : mb_substr($stem, 0, 120) . '.' . $extension;
    }

    public static function uniqueName(string $directory, string $name): string
    {
        if (!file_exists($directory . DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }

        $stem = pathinfo($name, PATHINFO_FILENAME);
        $ext  = pathinfo($name, PATHINFO_EXTENSION);

        for ($i = 2; $i < 1000; $i++) {
            $candidate = "$stem($i).$ext";
            if (!file_exists($directory . DIRECTORY_SEPARATOR . $candidate)) {
                return $candidate;
            }
        }

        return Str::random(16) . '.' . $ext;
    }

    /** Convertit en WebP si GD le permet, sinon stocke le fichier tel quel. */
    public static function storeImageBytes(string $binary, string $extension, string $folder = 'products'): ?string
    {
        if ($binary === '') {
            return null;
        }

        $webp = ImageOptimizer::supported() ? ImageOptimizer::toWebp($binary) : null;
        if ($webp !== null) {
            $path = "$folder/" . Str::random(40) . '.webp';
            Storage::disk('public')->put($path, $webp);

            return $path;
        }

        $extension = in_array(strtolower($extension), self::IMAGE_EXTENSIONS, true) ? strtolower($extension) : 'jpg';
        $path = "$folder/" . Str::random(40) . '.' . $extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /** Supprime les imports abandonnés depuis plus de 24 h. */
    public static function purgeStale(): void
    {
        $disk   = Storage::disk('local');
        $cutoff = now()->subHours(self::TMP_TTL_HOURS)->getTimestamp();

        foreach ($disk->directories(self::TMP_DIR) as $directory) {
            try {
                $meta = $directory . '/meta.json';
                $reference = $disk->exists($meta) ? $disk->lastModified($meta) : 0;
                if ($reference < $cutoff) {
                    $disk->deleteDirectory($directory);
                }
            } catch (\Throwable $e) {
                Log::warning('Purge des imports : dossier non supprimé', ['dir' => $directory, 'error' => $e->getMessage()]);
            }
        }
    }
}
