<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    // GET /api/pages — public (pages publiées uniquement)
    public function index(): JsonResponse
    {
        $pages = Page::published()->get(['id', 'title', 'slug', 'sort_order']);

        return response()->json(['data' => $pages]);
    }

    // GET /api/pages/{slug} — public
    public function show(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();

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
            'content'          => 'nullable|string',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published'     => 'boolean',
            'sort_order'       => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

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
            'content'          => 'nullable|string',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published'     => 'sometimes|boolean',
            'sort_order'       => 'sometimes|integer|min:0',
        ]);

        if (isset($validated['title'])) {
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
