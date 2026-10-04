<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Product;
use App\Support\Notifier;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GET /sitemap.xml — plan du site pour Google, généré depuis la base.
 * Référencé dans le robots.txt du frontend.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = collect([
                ['', 'daily', '1.0'],
                ['shop', 'daily', '0.9'],
                ['devis', 'monthly', '0.8'],
                ['about', 'monthly', '0.6'],
                ['contact', 'monthly', '0.6'],
                ['gallery', 'monthly', '0.5'],
                ['blog', 'weekly', '0.7'],
                ['suivi-commande', 'yearly', '0.3'],
            ])->map(fn($u) => ['loc' => Notifier::frontendUrl($u[0]), 'freq' => $u[1], 'prio' => $u[2], 'mod' => null]);

            Product::active()->get(['id', 'name', 'updated_at'])->each(function ($p) use ($urls) {
                $urls->push([
                    'loc'  => Notifier::frontendUrl('shop/' . $p->id . '-' . Str::slug($p->name)),
                    'freq' => 'weekly', 'prio' => '0.8', 'mod' => $p->updated_at,
                ]);
            });

            // Produits : on invalide le cache à chaque modification produit (voir Product::booted)
            $this->pushPublished(BlogPost::class, 'blog/', $urls, '0.6');
            $this->pushPublished(Page::class, 'pages/', $urls, '0.5');

            $body = $urls->map(function ($u) {
                $mod = $u['mod'] ? '<lastmod>' . $u['mod']->toAtomString() . '</lastmod>' : '';
                return '<url><loc>' . e($u['loc']) . "</loc>{$mod}<changefreq>{$u['freq']}</changefreq><priority>{$u['prio']}</priority></url>";
            })->implode("\n");

            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
                . $body . "\n</urlset>";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Ajoute les contenus publiés (blog, pages CMS). */
    private function pushPublished(string $model, string $prefix, $urls, string $prio): void
    {
        $model::published()->get(['slug', 'updated_at'])->each(function ($row) use ($urls, $prefix, $prio) {
            $urls->push([
                'loc'  => Notifier::frontendUrl($prefix . $row->slug),
                'freq' => 'monthly', 'prio' => $prio, 'mod' => $row->updated_at,
            ]);
        });
    }
}
