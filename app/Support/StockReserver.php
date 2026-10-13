<?php

namespace App\Support;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Décrémente le stock de façon sûre face à la concurrence.
 *
 * Vérifier le stock puis le décrémenter en deux temps laisse passer la survente :
 * deux clients sur le dernier article passent tous les deux la vérification.
 * Ici la lecture se fait sous `SELECT … FOR UPDATE`, à l'intérieur de la
 * transaction de commande : le second acheteur attend le premier et se voit
 * refuser proprement.
 */
class StockReserver
{
    /**
     * @param  iterable<array{product: Product, quantity: int}>  $lines
     * @return array<int, Product>  les produits rechargés sous verrou, indexés par id
     *
     * @throws InsufficientStockException
     */
    public static function reserve(iterable $lines): array
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('StockReserver::reserve doit être appelé dans une transaction.');
        }

        // Quantités cumulées : un même produit peut apparaître sur deux lignes
        $wanted = [];
        foreach ($lines as $line) {
            $id = (int) $line['product']->id;
            $wanted[$id] = ($wanted[$id] ?? 0) + (int) $line['quantity'];
        }

        if ($wanted === []) {
            return [];
        }

        // Ordre stable des identifiants : deux commandes concurrentes verrouillent
        // les mêmes lignes dans le même ordre, donc pas d'interblocage croisé.
        $ids = array_keys($wanted);
        sort($ids);

        $locked = Product::whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($wanted as $id => $quantity) {
            $product = $locked->get($id);

            if (!$product) {
                throw new InsufficientStockException("Produit #$id", 0, $quantity);
            }
            if ((int) $product->stock < $quantity) {
                throw new InsufficientStockException($product->name, (int) $product->stock, $quantity);
            }
        }

        foreach ($wanted as $id => $quantity) {
            $locked->get($id)->decrement('stock', $quantity);
        }

        return $locked->all();
    }
}
