<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Models;

use App\Modules\Fulfillment\Enums\PickLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PickLineStatus $status
 */
class PickLine extends Model
{
    protected $fillable = [
        'pick_list_id', 'order_line_id', 'product_id', 'name', 'sale_unit', 'ordered_quantity', 'unit_price_minor',
        'substitution', 'status', 'picked_quantity', 'final_product_id', 'final_name', 'final_unit_price_minor',
    ];

    protected function casts(): array
    {
        return [
            'status' => PickLineStatus::class,
            'ordered_quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'picked_quantity' => 'integer',
            'final_unit_price_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<PickList, $this> */
    public function pickList(): BelongsTo
    {
        return $this->belongsTo(PickList::class);
    }

    /**
     * Quantité maximale servable : pièces = quantité commandée ; produits au poids ou au volume =
     * quantité commandée + marge de pesée (le client n'est jamais débité au-delà).
     */
    public function maxQuantity(): int
    {
        if ($this->sale_unit === 'piece') {
            return $this->ordered_quantity;
        }

        return intdiv($this->ordered_quantity * (100 + (int) config('fulfillment.weight_margin_percent')), 100);
    }
}
