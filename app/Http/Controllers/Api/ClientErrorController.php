<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/client-errors
 * Reçoit les erreurs JavaScript des visiteurs (page blanche, bug d'affichage…)
 * et les écrit dans storage/logs/client-AAAA-MM-JJ.log.
 * Sans dépendance externe ; remplaçable plus tard par Sentry.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'message'   => 'required|string|max:1000',
            'stack'     => 'nullable|string|max:4000',
            'url'       => 'nullable|string|max:500',
            'source'    => 'nullable|string|in:onerror,unhandledrejection,boundary,manual',
            'component' => 'nullable|string|max:2000',
        ]);

        Log::channel('client')->error($data['message'], [
            'source'     => $data['source'] ?? null,
            'url'        => $data['url'] ?? null,
            'stack'      => $data['stack'] ?? null,
            'component'  => $data['component'] ?? null,
            'user_agent' => substr((string) $request->userAgent(), 0, 300),
            'user_id'    => auth('sanctum')->id(),
        ]);

        return response()->noContent();
    }
}
