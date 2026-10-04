<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\CacheVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    // GET /api/pages — public (pages publiées uniquement)
    public function index(Request $request): JsonResponse
    {
        // GET /api/pages?slugs=accueil,contact : contenu complet de plusieurs pages
        // en une seule requête (le site charge 6 blocs CMS à chaque visite)
        if ($request->filled('slugs')) {
            $slugs = collect(explode(',', (string) $request->slugs))
                ->map(fn($s) => trim($s))->filter()->unique()->take(20)->sort()->values();

            $data = CacheVersion::remember('pages', ['batch', $slugs->implode(',')], 3600,
                fn() => Page::published()->whereIn('slug', $slugs)->get()->keyBy('slug')->toArray());

            return response()->json(['data' => (object) $data]);
        }

        $pages = CacheVersion::remember('pages', ['index'], 3600,
            fn() => Page::published()->get(['id', 'title', 'slug', 'sort_order'])->toArray());

        return response()->json(['data' => $pages]);
    }

    // GET /api/pages/{slug} — public
    public function show(string $slug): JsonResponse
    {
        $page = CacheVersion::remember('pages', ['show', $slug], 3600,
            fn() => Page::where('slug', $slug)->where('is_published', true)->first()?->toArray());

        abort_if(!$page, 404, 'Ressource introuvable');

        return response()->json(['data' => $page]);
    }

    // GET /api/admin/pages — admin (toutes les pages)
    public function adminIndex(): JsonResponse
    {
        $pages = Page::orderBy('sort_order')->get();

        return response()->json(['data' => $pages]);
    }

    // POST /api/admin/pages — admin
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255',
            'content'          => 'nullable|string',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published'     => 'boolean',
            'sort_order'       => 'integer|min:0',
        ]);

        // Use provided slug (slugified) or generate from title
        if (!empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        } else {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $base  = $validated['slug'];
        $count = 1;
        while (Page::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = "{$base}-{$count}";
            $count++;
        }

        $page = Page::create($validated);

        return response()->json(['message' => 'Page créée', 'data' => $page], 201);
    }

    // PUT /api/admin/pages/{id} — admin
    public function update(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'slug'             => 'nullable|string|max:255',
            'content'          => 'nullable|string',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published'     => 'sometimes|boolean',
            'sort_order'       => 'sometimes|integer|min:0',
        ]);

        if ($request->has('slug') && !empty($validated['slug'])) {
            // Slug explicitly provided — use it slugified
            $validated['slug'] = Str::slug($validated['slug']);
        } elseif (!$request->has('slug') && isset($validated['title'])) {
            // No slug in request but title changed — auto-generate from title
            $validated['slug'] = Str::slug($validated['title']);
        }

        $page->update($validated);

        return response()->json(['message' => 'Page mise à jour', 'data' => $page->fresh()]);
    }

    // DELETE /api/admin/pages/{id} — admin
    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

        return response()->json(['message' => 'Page supprimée']);
    }
}
