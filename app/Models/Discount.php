<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Code promo. `check()` concentre toutes les raisons de refus pour que le client
 * sache ce qui bloque (« code expiré », « panier minimum de 10 000 XAF »)
 * au lieu d'un « code invalide » générique.
 */
class Discount extends Model
{
    protected $fillable = [
        'code',
        'label',
        'type',
        'value',
        'min_subtotal',
        'max_uses',
        'max_uses_per_user',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value'             => 'decimal:2',
            'min_subtotal'      => 'decimal:2',
            'max_uses'          => 'integer',
            'max_uses_per_user' => 'integer',
            'used_count'        => 'integer',
            'starts_at'         => 'datetime',
            'ends_at'           => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        // Le client tape « bienvenue10 » ou « Bienvenue10 » : on normalise
        $this->attributes['code'] = mb_strtoupper(trim($value));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function findByCode(?string $code): ?self
    {
        $code = mb_strtoupper(trim((string) $code));

        return $code === '' ? null : static::where('code', $code)->first();
    }

    /**
     * Raison du refus, ou null si le code s'applique.
     *
     * @return string|null
     */
    public function check(float $subtotal, ?User $user = null): ?string
    {
        if (!$this->is_active) {
            return "Ce code promo n'est plus actif.";
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return "Ce code promo n'est pas encore valable.";
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'Ce code promo a expiré.';
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return "Ce code promo a atteint son nombre maximum d'utilisations.";
        }
        if ($this->min_subtotal !== null && $subtotal < (float) $this->min_subtotal) {
            return 'Ce code exige un panier de ' . number_format((float) $this->min_subtotal, 0, ',', ' ') . ' XAF minimum.';
        }

        if ($user && $this->max_uses_per_user !== null) {
            $alreadyUsed = Order::where('user_id', $user->id)
                ->where('discount_code', $this->code)
                ->whereNot('status', 'cancelled')
                ->count();

            if ($alreadyUsed >= $this->max_uses_per_user) {
                return 'Vous avez déjà utilisé ce code promo.';
            }
        }

        return null;
    }

    /** Montant de la remise, jamais supérieur au sous-panier. */
    public function amountFor(float $subtotal): float
    {
        $amount = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($amount, $subtotal), 2);
    }
}
