<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Redimensionne et convertit les images en WebP avant stockage.
 * Une photo de téléphone de 3 Mo devient ~150 Ko : pages bien plus rapides en 3G/4G.
 * Sans support WebP (GD absent), l'image d'origine est stockée telle quelle.
 */
class ImageOptimizer
{
    public const MAX_SIZE = 1600;   // côté le plus long, en pixels
    public const QUALITY  = 82;

    public static function supported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /** Stocke un fichier envoyé, optimisé si possible. Retourne le chemin relatif. */
    public static function store(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        if (self::supported()) {
            $webp = self::toWebp((string) file_get_contents($file->getRealPath()));
            if ($webp !== null) {
                $path = trim($folder, '/') . '/' . Str::random(40) . '.webp';
                Storage::disk($disk)->put($path, $webp);
                return $path;
            }
        }

        return $file->store($folder, $disk);
    }

    /**
     * Convertit une image déjà stockée. Retourne le nouveau chemin, ou null si inchangée.
     * L'original est supprimé seulement si la conversion a réussi.
     */
    public static function convertStored(string $path, string $disk = 'public'): ?string
    {
        $storage = Storage::disk($disk);
        if (!self::supported() || str_ends_with(strtolower($path), '.webp') || !$storage->exists($path)) {
            return null;
        }

        $webp = self::toWebp((string) $storage->get($path));
        if ($webp === null) {
            return null;
        }

        $newPath = preg_replace('/\.[a-z0-9]+$/i', '', $path) . '.webp';
        $storage->put($newPath, $webp);
        $storage->delete($path);

        return $newPath;
    }

    /** Binaire WebP, ou null si l'image est illisible. */
    public static function toWebp(string $binary): ?string
    {
        try {
            $src = @imagecreatefromstring($binary);
            if (!$src) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $ratio = min(1, self::MAX_SIZE / max($w, $h));
            $nw = max(1, (int) round($w * $ratio));
            $nh = max(1, (int) round($h * $ratio));

            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            ob_start();
            imagewebp($dst, null, self::QUALITY);
            $out = ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return $out ?: null;
        } catch (\Throwable $e) {
            Log::warning('Optimisation image impossible', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
