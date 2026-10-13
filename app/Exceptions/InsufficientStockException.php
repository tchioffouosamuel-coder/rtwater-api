<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée depuis la transaction de commande quand le stock verrouillé ne suffit
 * plus. Son rôle est d'annuler la transaction tout en portant un message
 * affichable au client.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(
            "Stock insuffisant pour « {$productName} ». Disponible : {$available}, demandé : {$requested}."
        );
    }
}
