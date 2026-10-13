<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductImport;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assistant d'import massif de produits (Excel / CSV + images).
 *
 * L'admin télécharge le modèle, dépose son fichier (analyze → aperçu sans
 * écriture), vérifie, puis valide (commit). Le jeton renvoyé par l'analyse
 * évite de re-téléverser l'archive d'images.
 */
class ProductImportController extends Controller
{
    public function __construct(private readonly ProductImportService $service)
    {
    }

    /** Modèle XLSX prérempli (en-têtes, exemples, catégories existantes). */
    public function template(): StreamedResponse
    {
        $binary = $this->service->template();

        return response()->streamDownload(
            fn() => print($binary),
            'modele-import-produits.xlsx',
            [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    /** Étape 1 — lit le fichier et renvoie l'aperçu. Aucune écriture en base. */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'     => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'archive'  => 'nullable|file|mimes:zip|max:204800',
            'images'   => 'nullable|array|max:1000',
            'images.*' => 'file|image|max:8192',
        ], [
            'file.required' => 'Sélectionnez un fichier Excel ou CSV.',
            'file.mimes'    => 'Formats acceptés : .xlsx, .xls, .csv.',
            'file.max'      => 'Le fichier ne doit pas dépasser 20 Mo.',
            'archive.mimes' => "L'archive d'images doit être un fichier .zip.",
            'archive.max'   => "L'archive ne doit pas dépasser 200 Mo.",
        ]);

        try {
            $preview = $this->service->analyze(
                $validated['file'],
                $request->file('images', []) ?? [],
                $request->file('archive')
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $preview]);
    }

    /** Étape 2 — enregistre les produits de l'aperçu. */
    public function commit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'               => 'required|string|size:32|regex:/^[A-Za-z0-9]+$/',
            'update_existing'     => 'boolean',
            'create_categories'   => 'boolean',
            'allow_remote_images' => 'boolean',
        ]);

        try {
            $import = $this->service->commit(
                $validated['token'],
                $request->only(['update_existing', 'create_categories', 'allow_remote_images']),
                $request->user()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => "Import terminé : {$import->created_count} création(s), {$import->updated_count} mise(s) à jour.",
            'data'    => $this->present($import),
        ], Response::HTTP_CREATED);
    }

    /** Abandon de l'assistant : purge les fichiers temporaires. */
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

    /** Journal des imports précédents. */
    public function history(Request $request): JsonResponse
    {
        $imports = ProductImport::with('user:id,name')
            ->latest()
            ->paginate(min((int) ($request->per_page ?? 10), 50));

        return response()->json([
            'data'       => $imports->getCollection()->map(fn($i) => $this->present($i))->all(),
            'pagination' => [
                'current_page' => $imports->currentPage(),
                'last_page'    => $imports->lastPage(),
                'per_page'     => $imports->perPage(),
                'total'        => $imports->total(),
            ],
        ]);
    }

    private function present(ProductImport $import): array
    {
        return [
            'id'            => $import->id,
            'filename'      => $import->filename,
            'user'          => $import->user?->name,
            'rows_total'    => $import->rows_total,
            'created_count' => $import->created_count,
            'updated_count' => $import->updated_count,
            'skipped_count' => $import->skipped_count,
            'failed_count'  => $import->failed_count,
            'images_count'  => $import->images_count,
            'options'       => $import->options,
            'errors'        => $import->errors ?? [],
            'created_at'    => $import->created_at?->toIso8601String(),
        ];
    }
}
