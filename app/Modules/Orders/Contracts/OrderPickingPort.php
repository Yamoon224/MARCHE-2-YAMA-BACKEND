<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\PickResult;
use App\Modules\Orders\DTO\PickSource;
use App\Modules\Orders\Exceptions\InsufficientStock;

/** Port : Fulfillment lit les lignes à préparer et restitue le résultat sans connaître les modèles d'Orders. */
interface OrderPickingPort
{
    /** @return list<PickSource>|null null si la commande n'existe pas */
    public function linesFor(int $orderId): ?array;

    /**
     * Enregistre le résultat de la préparation : lignes finales, stock ajusté, total réel, puis commande « prête ».
     * Retourne l'écart (réel - facturé) : négatif = à rembourser, positif = à encaisser.
     *
     * @param  list<PickResult>  $results
     *
     * @throws InsufficientStock un produit de remplacement n'est pas disponible
     */
    public function applyResult(int $orderId, array $results, ?int $pickerId): int;
}
