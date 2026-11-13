<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Actions;

use App\Modules\Fulfillment\Enums\PickLineStatus;
use App\Modules\Fulfillment\Enums\PickListStatus;
use App\Modules\Fulfillment\Exceptions\PickingNotAllowed;
use App\Modules\Fulfillment\Models\PickLine;
use App\Modules\Orders\Contracts\CatalogPriceReader;

final class SubstituteLineAction
{
    public function __construct(private readonly CatalogPriceReader $catalog) {}

    /**
     * Remplace un article en rupture par un équivalent, si le client l'a accepté. Le client ne paie jamais
     * plus cher que l'article d'origine : le prix du remplaçant est plafonné au prix commandé.
     *
     * @throws PickingNotAllowed
     */
    public function execute(PickLine $line, int $substituteProductId, int $quantity): PickLine
    {
        if ($line->pickList->status !== PickListStatus::InProgress) {
            throw PickingNotAllowed::notInProgress();
        }

        if ($line->substitution === 'refuse') {
            throw PickingNotAllowed::substitutionRefused();
        }

        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité du remplaçant doit être positive.');
        }

        if ($quantity > $line->maxQuantity()) {
            throw PickingNotAllowed::quantityAbove($line->maxQuantity());
        }

        $substitute = $this->catalog->findOrderable([$substituteProductId])[$substituteProductId]
            ?? throw PickingNotAllowed::substituteUnavailable();

        if ($substitute->saleUnit !== $line->sale_unit) {
            throw PickingNotAllowed::substituteUnitMismatch();
        }

        $line->update([
            'status' => PickLineStatus::Substituted,
            'picked_quantity' => $quantity,
            'final_product_id' => $substitute->productId,
            'final_name' => $substitute->name,
            'final_unit_price_minor' => min($substitute->priceMinor, $line->unit_price_minor),
        ]);

        return $line;
    }
}
