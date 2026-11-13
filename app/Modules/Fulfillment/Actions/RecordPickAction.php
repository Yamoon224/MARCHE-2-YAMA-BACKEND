<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Actions;

use App\Modules\Fulfillment\Enums\PickLineStatus;
use App\Modules\Fulfillment\Enums\PickListStatus;
use App\Modules\Fulfillment\Exceptions\PickingNotAllowed;
use App\Modules\Fulfillment\Models\PickLine;

final class RecordPickAction
{
    /**
     * Enregistre la quantité réellement prélevée (pièces comptées, ou poids en grammes / volume en ml).
     * 0 = article en rupture. Au-dessus du plafond (commandé + marge de pesée) : refusé, car le client
     * ne peut pas être débité de plus que ce qu'il a autorisé.
     *
     * @throws PickingNotAllowed
     */
    public function execute(PickLine $line, int $quantity): PickLine
    {
        if ($line->pickList->status !== PickListStatus::InProgress) {
            throw PickingNotAllowed::notInProgress();
        }

        if ($quantity < 0) {
            throw new \InvalidArgumentException('La quantité ne peut pas être négative.');
        }

        if ($quantity > $line->maxQuantity()) {
            throw PickingNotAllowed::quantityAbove($line->maxQuantity());
        }

        $line->update([
            'picked_quantity' => $quantity,
            // Servi au moins la quantité commandée = prélevé ; en dessous (ou rien) = manque.
            'status' => $quantity >= $line->ordered_quantity || ($line->sale_unit !== 'piece' && $quantity > 0)
                ? PickLineStatus::Picked
                : PickLineStatus::Short,
            // Un nouveau prélèvement annule un remplacement antérieur.
            'final_product_id' => null, 'final_name' => null, 'final_unit_price_minor' => null,
        ]);

        return $line;
    }
}
