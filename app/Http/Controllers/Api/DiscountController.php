<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Codes promo : administration, et vérification depuis la page de paiement. */
class DiscountController extends Controller
{
    /** GET /api/admin/discounts */
    public function index(Request $request): JsonResponse
    {
        $discounts = Discount::query()
            ->when($request->search, fn($q, $v) => $q->where('code', 'like', '%' . mb_strtoupper($v) . '%'))
            ->latest()
            ->paginate(min((int) ($request->per_page ?? 20), 100));

        return response()->json([
            'data'       => $discounts->getCollection()->map(fn($d) => $this->present($d))->all(),
            'pagination' => [
                'current_page' => $discounts->currentPage(),
                'last_page'    => $discounts->lastPage(),
                'total'        => $discounts->total(),
            ],
        ]);
    }

    /** POST /api/admin/discounts */
    public function store(Request $request): JsonResponse
    {
        // refresh() : sans lui l'instance ignore les valeurs par defaut de la
        // base (is_active, used_count) et la reponse annonce un code inactif.
        $discount = Discount::create($this->validated($request))->refresh();

        return response()->json([
            'message' => 'Code promo créé',
            'data'    => $this->present($discount),
        ], 201);
    }

    /** PUT /api/admin/discounts/{discount} */
    public function update(Request $request, Discount $discount): JsonResponse
    {
        $discount->update($this->validated($request, $discount));

        return response()->json([
            'message' => 'Code promo mis à jour',
            'data'    => $this->present($discount->fresh()),
        ]);
    }

    /** DELETE /api/admin/discounts/{discount} */
    public function destroy(Discount $discount): JsonResponse
    {
        // Un code déjà utilisé est désactivé, pas supprimé : les commandes
        // passées y font référence et l'historique doit rester lisible.
        if ($discount->used_count > 0) {
            $discount->update(['is_active' => false]);

            return response()->json([
                'message' => 'Ce code a déjà servi : il a été désactivé plutôt que supprimé.',
                'data'    => $this->present($discount->fresh()),
            ]);
        }

        $discount->delete();

        return response()->json(['message' => 'Code promo supprimé']);
    }

    /**
     * POST /api/discounts/check — public.
     * Vérifie un code pour un sous-panier donné, sans rien engager.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'     => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $discount = Discount::findByCode($validated['code']);
        $subtotal = (float) $validated['subtotal'];

        if (!$discount) {
            return response()->json(['message' => "Ce code promo n'existe pas."], 422);
        }

        if ($reason = $discount->check($subtotal, $request->user())) {
            return response()->json(['message' => $reason], 422);
        }

        return response()->json([
            'data' => [
                'code'   => $discount->code,
                'type'   => $discount->type,
                'value'  => (float) $discount->value,
                'amount' => $discount->amountFor($subtotal),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════

    private function validated(Request $request, ?Discount $existing = null): array
    {
        return $request->validate([
            'code'  => [
                $existing ? 'sometimes' : 'required',
                'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('discounts', 'code')->ignore($existing?->id),
            ],
            'label'             => 'nullable|string|max:150',
            'type'              => [$existing ? 'sometimes' : 'required', 'in:percent,fixed'],
            'value'             => [$existing ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'min_subtotal'      => 'nullable|numeric|min:0',
            'max_uses'          => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at'         => 'nullable|date',
            'ends_at'           => 'nullable|date|after:starts_at',
            'is_active'         => 'boolean',
        ], [
            'code.regex'     => 'Le code ne peut contenir que des lettres, chiffres, tirets et underscores.',
            'code.unique'    => 'Ce code existe déjà.',
            'ends_at.after'  => 'La date de fin doit suivre la date de début.',
        ]);
    }

    private function present(Discount $d): array
    {
        return [
            'id'                => $d->id,
            'code'              => $d->code,
            'label'             => $d->label,
            'type'              => $d->type,
            'value'             => (float) $d->value,
            'min_subtotal'      => $d->min_subtotal !== null ? (float) $d->min_subtotal : null,
            'max_uses'          => $d->max_uses,
            'max_uses_per_user' => $d->max_uses_per_user,
            'used_count'        => $d->used_count,
            'starts_at'         => $d->starts_at?->toIso8601String(),
            'ends_at'           => $d->ends_at?->toIso8601String(),
            'is_active'         => $d->is_active,
            // Statut lisible : l'admin voit d'un coup d'œil si le code tourne
            'state'             => $this->state($d),
            'created_at'        => $d->created_at?->toIso8601String(),
        ];
    }

    private function state(Discount $d): string
    {
        if (!$d->is_active) {
            return 'inactive';
        }
        if ($d->starts_at && $d->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($d->ends_at && $d->ends_at->isPast()) {
            return 'expired';
        }
        if ($d->max_uses !== null && $d->used_count >= $d->max_uses) {
            return 'exhausted';
        }

        return 'running';
    }
}
