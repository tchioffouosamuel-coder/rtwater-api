<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fiche technique d'un produit : texte, image ou PDF.
 *
 * Centralisé ici parce que les trois opérations (création, mise à jour,
 * suppression) doivent appliquer les mêmes règles, dont une qui se perd
 * facilement : l'ancien fichier n'est supprimé qu'une fois le nouveau en place.
 */
class DatasheetStorage
{
    public const FOLDER = 'datasheets';

    /** 10 Mo : au-delà, un PDF de fiche technique est mal préparé. */
    public const MAX_KILOBYTES = 10240;

    /** Extensions acceptées. SVG est exclu : il peut porter du script. */
    public const IMAGE_MIMES = ['jpeg', 'jpg', 'png', 'webp'];

    /**
     * Règles de validation du bloc « fiche technique ».
     *
     * @param  bool  $partial  true pour une mise à jour : les champs absents
     *                         de la requête ne sont pas touchés
     * @return array<string, mixed>
     */
    public static function rules(bool $partial = false): array
    {
        $mimes = implode(',', array_merge(self::IMAGE_MIMES, ['pdf']));

        return [
            // « none » retire la fiche. Une valeur explicite plutôt qu'une chaîne
            // vide : FormData ne transporte pas null, et dépendre de la conversion
            // des chaînes vides par le framework rendrait le retrait silencieusement
            // inopérant si ce middleware changeait.
            'datasheet_type' => ($partial ? 'sometimes|' : '') . 'nullable|in:none,text,image,pdf',

            // required_if : choisir « texte » sans rien écrire laisse une section vide
            // sur la fiche produit, ce qui se voit en ligne et pas dans le backoffice.
            'datasheet_text' => 'nullable|string|max:20000|required_if:datasheet_type,text',

            'datasheet_file' => [
                'nullable',
                'file',
                'mimes:' . $mimes,
                'max:' . self::MAX_KILOBYTES,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'datasheet_type.in'          => 'Le type de fiche technique doit être « texte », « image » ou « pdf ».',
            'datasheet_text.required_if' => 'Saisissez le contenu de la fiche technique.',
            'datasheet_text.max'         => 'La fiche technique ne doit pas dépasser 20 000 caractères.',
            'datasheet_file.mimes'       => 'La fiche technique doit être une image (JPG, PNG, WebP) ou un PDF.',
            'datasheet_file.max'         => 'Le fichier ne doit pas dépasser 10 Mo.',
        ];
    }

    /**
     * Calcule les attributs de fiche technique à écrire sur le produit.
     *
     * Retourne un tableau vide quand la requête ne parle pas de fiche technique :
     * une mise à jour de prix ne doit pas effacer la fiche existante.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function attributesFor(
        array $validated,
        ?UploadedFile $file,
        ?Product $existing = null,
    ): array {
        $hasType = array_key_exists('datasheet_type', $validated);

        if (!$hasType && !$file) {
            return [];
        }

        $requested = $validated['datasheet_type'] ?? null;
        if ($requested === '' || $requested === 'none') {
            $requested = null;
        }

        // Retrait explicite : il prime, même si un fichier accompagne la requête
        if ($hasType && $requested === null) {
            return [
                'datasheet_type'     => null,
                'datasheet_text'     => null,
                'datasheet_path'     => null,
                'datasheet_filename' => null,
            ];
        }

        // Un fichier est fourni : c'est lui qui détermine le type (image ou PDF).
        // On ne se fie pas au type déclaré, qui peut ne pas correspondre au fichier
        // réellement choisi — et un fichier envoyé sans type déclaré doit marcher.
        if ($file) {
            return array_merge(['datasheet_text' => null], self::storeFile($file));
        }

        $type = $requested ?? ($hasType ? null : $existing?->datasheet_type);

        if ($type === 'text') {
            return [
                'datasheet_type'     => 'text',
                'datasheet_text'     => $validated['datasheet_text'] ?? $existing?->datasheet_text,
                'datasheet_path'     => null,
                'datasheet_filename' => null,
            ];
        }

        // Type « image » ou « pdf » annoncé sans nouveau fichier : on conserve
        // celui déjà en place. Cas courant — l'admin modifie le prix sans
        // retoucher la fiche technique.
        if (($type === 'image' || $type === 'pdf') && $existing?->datasheet_path) {
            return [
                'datasheet_type'     => $existing->datasheet_type,
                'datasheet_text'     => null,
                'datasheet_path'     => $existing->datasheet_path,
                'datasheet_filename' => $existing->datasheet_filename,
            ];
        }

        return [];
    }

    /**
     * Écrit le fichier et renvoie les attributs correspondants.
     *
     * @return array{datasheet_type:string, datasheet_path:string, datasheet_filename:string}
     */
    public static function storeFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $isPdf     = $extension === 'pdf' || $file->getMimeType() === 'application/pdf';

        if ($isPdf) {
            $path = $file->store(self::FOLDER, 'public');

            return [
                'datasheet_type'     => 'pdf',
                'datasheet_path'     => $path,
                'datasheet_filename' => self::safeFilename($file),
            ];
        }

        // Image : convertie en WebP mais moins réduite qu'une photo produit —
        // un tableau de caractéristiques doit rester lisible.
        $path = ImageOptimizer::store($file, self::FOLDER, 'public', ImageOptimizer::DATASHEET_MAX_SIZE);

        return [
            'datasheet_type'     => 'image',
            'datasheet_path'     => $path,
            'datasheet_filename' => self::safeFilename($file),
        ];
    }

    /**
     * Supprime l'ancien fichier, une fois le nouveau enregistré.
     *
     * À appeler après l'écriture du produit : si l'enregistrement échoue, le
     * fichier d'origine est toujours là.
     */
    public static function forget(?string $oldPath, ?string $newPath = null): void
    {
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    /** Nom d'origine assaini, pour le libellé du lien de téléchargement. */
    private static function safeFilename(UploadedFile $file): string
    {
        $name = $file->getClientOriginalName();
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'bin';

        $stem = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '-', $stem) ?? '';
        $stem = trim(str_replace('..', '.', $stem), '-. ');

        if ($stem === '') {
            $stem = 'fiche-technique-' . Str::random(6);
        }

        return Str::limit($stem, 80, '') . '.' . $ext;
    }
}
