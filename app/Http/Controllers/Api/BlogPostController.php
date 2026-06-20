<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    // GET /api/blog — public
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::published()
            ->select('id', 'title', 'slug', 'excerpt', 'cover_image', 'tags', 'published_at', 'author_id')
            ->with('author:id,name')
            ->when($request->search, fn($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest('published_at')
            ->paginate(12);

        return response()->json([
            'data'       => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'total'        => $posts->total(),
            ],
        ]);
    }

    // GET /api/blog/{slug} — public
    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::where('slug', $slug)->published()->with('author:id,name')->firstOrFail();

        return response()->json(['data' => $post]);
    }

    // GET /api/admin/blog — admin (tous les articles dont drafts)
    public function adminIndex(Request $request): JsonResponse
    {
        $posts = BlogPost::with('author:id,name')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'       => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'total'        => $posts->total(),
            ],
        ]);
    }

    // POST /api/admin/blog — admin
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'excerpt'      => 'nullable|string|max:500',
            'content'      => 'required|string',
            'cover_image'  => 'nullable|string',
            'status'       => 'in:draft,published,archived',
            'tags'         => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        $validated['slug']      = Str::slug($validated['title']);
        $validated['author_id'] = $request->user()->id;

        // Éviter les slugs en doublon
        $base  = $validated['slug'];
        $count = 1;
        while (BlogPost::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = "{$base}-{$count}";
            $count++;
        }

        if (($validated['status'] ?? 'draft') === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post = BlogPost::create($validated);

        return response()->json(['message' => 'Article créé', 'data' => $post->load('author:id,name')], 201);
    }

    // PUT /api/admin/blog/{id} — admin
    public function update(Request $request, BlogPost $blogPost): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'excerpt'      => 'nullable|string|max:500',
            'content'      => 'sometimes|string',
            'cover_image'  => 'nullable|string',
            'status'       => 'sometimes|in:draft,published,archived',
            'tags'         => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        if (($validated['status'] ?? null) === 'published' && empty($blogPost->published_at)) {
            $validated['published_at'] = now();
        }

        $blogPost->update($validated);

        return response()->json(['message' => 'Article mis à jour', 'data' => $blogPost->fresh('author:id,name')]);
    }

    // DELETE /api/admin/blog/{id} — admin
    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $blogPost->delete();

        return response()->json(['message' => 'Article supprimé']);
    }
}
