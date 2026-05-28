<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function uploadImage(Request $request): JsonResponse
    // Endpoint générique pour uploader une image
    {
        $request->validate([
            'image'  => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            // required → obligatoire
            // image   → doit être une image
            // mimes   → formats acceptés : jpeg, png, jpg, webp
            // max:2048 → taille max 2 Mo (2048 Ko)

            'folder' => 'required|in:products,services,categories',
            // Le dossier de destination
            // in: → seulement ces valeurs autorisées
            // Évite qu'on stocke des fichiers n'importe où
        ]);

        $path = $request->file('image')->store(
            $request->folder,
            'public'
        );
        // ->store(dossier, disk)
        // dossier → sous-dossier dans storage/app/public/
        // 'public' → utilise le disk "public" configuré dans config/filesystems.php
        //
        // Laravel génère automatiquement un nom unique pour le fichier
        // ex: "products/AbCdEf123456.jpg"
        // Évite les conflits de noms

        return response()->json([
            'message'  => 'Image uploadée avec succès',
            'path'     => $path,
            // Chemin relatif stocké en DB : "products/AbCdEf123456.jpg"

            'url'      => Storage::url($path),
            // URL publique complète :
            // "http://127.0.0.1:8000/storage/products/AbCdEf123456.jpg"
            // Storage::url() génère l'URL correcte automatiquement
        ], 201);
    }

    public function deleteImage(Request $request): JsonResponse
    // Supprime une image du storage
    {
        $request->validate([
            'path' => 'required|string',
            // Le chemin de l'image à supprimer
            // ex: "products/AbCdEf123456.jpg"
        ]);

        $path = $request->path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'Image introuvable',
            ], 404);
            // Vérifie que le fichier existe avant de le supprimer
        }

        Storage::disk('public')->delete($path);
        // Supprime le fichier du storage
        // disk('public') → cherche dans storage/app/public/

        return response()->json([
            'message' => 'Image supprimée avec succès',
        ]);
    }
}
