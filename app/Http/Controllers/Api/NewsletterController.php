<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Lettre d'information : inscription publique, consultation et export en admin. */
class NewsletterController extends Controller
{
    /** POST /api/newsletter — public. */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'  => 'required|email|max:150',
            'source' => 'nullable|string|max:50',
        ], [
            'email.required' => 'Indiquez votre adresse e-mail.',
            'email.email'    => "Cette adresse e-mail n'est pas valide.",
        ]);

        $email = mb_strtolower(trim($validated['email']));

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);

        // Une réinscription après désabonnement réactive la ligne existante
        $alreadySubscribed = $subscriber->exists && $subscriber->unsubscribed_at === null;

        $subscriber->fill([
            'source'          => $validated['source'] ?? 'footer',
            'unsubscribed_at' => null,
        ])->save();

        return response()->json([
            'message' => $alreadySubscribed
                ? "Vous êtes déjà inscrit à notre lettre d'information."
                : 'Inscription confirmée. Merci !',
        ], $alreadySubscribed ? 200 : 201);
    }

    /** DELETE /api/newsletter — public, désinscription. */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email|max:150']);

        NewsletterSubscriber::where('email', mb_strtolower(trim($validated['email'])))
            ->update(['unsubscribed_at' => now()]);

        // Réponse identique que l'adresse existe ou non : ne pas révéler la liste
        return response()->json(['message' => 'Désinscription enregistrée.']);
    }

    /** GET /api/admin/newsletter */
    public function index(Request $request): JsonResponse
    {
        $subscribers = NewsletterSubscriber::query()
            ->when($request->search, fn($q, $v) => $q->where('email', 'like', "%$v%"))
            ->when($request->status === 'active', fn($q) => $q->whereNull('unsubscribed_at'))
            ->when($request->status === 'unsubscribed', fn($q) => $q->whereNotNull('unsubscribed_at'))
            ->latest()
            ->paginate(min((int) ($request->per_page ?? 25), 100));

        return response()->json([
            'data' => $subscribers->getCollection()->map(fn($s) => [
                'id'              => $s->id,
                'email'           => $s->email,
                'source'          => $s->source,
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
                'created_at'      => $s->created_at?->toIso8601String(),
            ])->all(),
            'pagination' => [
                'current_page' => $subscribers->currentPage(),
                'last_page'    => $subscribers->lastPage(),
                'total'        => $subscribers->total(),
            ],
            'stats' => [
                'active'       => NewsletterSubscriber::whereNull('unsubscribed_at')->count(),
                'unsubscribed' => NewsletterSubscriber::whereNotNull('unsubscribed_at')->count(),
            ],
        ]);
    }

    /** GET /api/admin/newsletter/export — CSV des inscrits actifs. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : sinon Excel sous Windows casse les accents
            fputcsv($out, ['E-mail', 'Source', 'Inscrit le'], ';');

            NewsletterSubscriber::whereNull('unsubscribed_at')
                ->orderBy('id')
                ->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->email, $row->source, $row->created_at?->format('d/m/Y')], ';');
                    }
                });

            fclose($out);
        }, 'newsletter-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
