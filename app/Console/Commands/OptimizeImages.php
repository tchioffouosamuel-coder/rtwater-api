<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Service;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;

/**
 * Convertit en WebP les images produits et services déjà en ligne.
 * À lancer une fois après déploiement :  php artisan images:optimize
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--dry-run : Affiche ce qui serait converti sans rien modifier}';

    protected $description = 'Redimensionne et convertit en WebP les images produits et services existantes';

    public function handle(): int
    {
        if (!ImageOptimizer::supported()) {
            $this->error("L'extension GD avec WebP n'est pas disponible sur ce serveur.");
            return self::FAILURE;
        }

        $converted = 0;
        $skipped = 0;

        foreach ([Product::class, Service::class] as $model) {
            $model::query()
                ->whereNotNull('image_url')
                ->where('image_url', 'not like', '%.webp')
                ->where('image_url', 'not like', 'http%')
                ->each(function ($record) use (&$converted, &$skipped) {
                    if ($this->option('dry-run')) {
                        $this->line("À convertir : {$record->image_url}");
                        $converted++;
                        return;
                    }

                    $newPath = ImageOptimizer::convertStored($record->image_url);
                    if ($newPath) {
                        // saveQuietly : pas besoin de régénérer sitemap et cache pour chaque image
                        $record->image_url = $newPath;
                        $record->saveQuietly();
                        $converted++;
                    } else {
                        $this->warn("Ignorée (fichier absent ou illisible) : {$record->image_url}");
                        $skipped++;
                    }
                });
        }

        $this->info(($this->option('dry-run') ? 'Images à convertir' : 'Images converties') . " : {$converted}. Ignorées : {$skipped}.");

        return self::SUCCESS;
    }
}
