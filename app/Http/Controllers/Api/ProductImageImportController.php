<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductImageImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Import d'images produit en masse.
 *
 * Second temps du flux d'import : le fichier Excel a déclaré les noms de
 * fichiers attendus, les photos arrivent ici et se rattachent toutes seules.
 */
class ProductImageImportController extends Controller
{
    public function __construct(private readonly ProductImageImportService $service)
    {
    }

    /** GET /api/admin/products/images/pending — produits en attente d'image. */
    public function pending(): JsonResponse
    {
        return response()->json($this->service->pendingReport());
    }

    /** POST /api/admin/products/images/analyze — aperçu des rattachements, sans écriture. */
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'archive'  => 'nullable|file|mimes:zip|max:204800',
            'images'   => 'nullable|array|max:1000',
            'images.*' => 'file|image|max:8192',
        ], [
            'archive.mimes' => "L'archive d'images doit être un fichier .zip.",
            'archive.max'   => "L'archive ne doit pas dépasser 200 Mo.",
            'images.max'    => 'Pas plus de 1000 images par import.',
            'images.*.max'  => 'Chaque image doit rester sous 8 Mo.',
        ]);

        if (!$request->hasFile('archive') && !$request->hasFile('images')) {
            return response()->json([
                'message' => 'Déposez une archive ZIP ou des images.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $preview = $this->service->analyze(
                $request->file('images', []) ?? [],
                $request->file('archive'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $preview]);
    }

    /** POST /api/admin/products/images/commit — rattache les images de l'aperçu. */
    public function commit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'            => 'required|string|size:32|regex:/^[A-Za-z0-9]+$/',
            'replace_existing' => 'boolean',
        ]);

        try {
            $result = $this->service->commit(
                $validated['token'],
                $request->only('replace_existing'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $total = $result['attached'] + $result['replaced'];

        return response()->json([
            'message' => $total > 0
                ? "$total image(s) rattachée(s)."
                : 'Aucune image rattachée.',
            'data'    => $result,
        ], Response::HTTP_CREATED);
    }

    /** POST /api/admin/products/images/discard — abandon, purge des fichiers. */
    public function discard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|size:32|regex:/^[A-Za-z0-9]+$/',
        ]);

        try {
            $this->service->forget($validated['token']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['message' => 'Import abandonné.']);
    }
}
