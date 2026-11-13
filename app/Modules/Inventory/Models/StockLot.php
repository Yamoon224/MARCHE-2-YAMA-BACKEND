<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockLotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_on
 */
class StockLot extends Model
{
    /** @use HasFactory<StockLotFactory> */
    use HasFactory;

    protected $fillable = [
        'warehouse_id', 'product_id', 'lot_code', 'expires_on',
        'quantity_on_hand', 'quantity_reserved', 'is_blocked',
    ];

    protected static function newFactory(): StockLotFactory
    {
        return StockLotFactory::new();
    }

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'is_blocked' => 'boolean',
        ];
    }

    /** Disponible = physique - réservé. */
    public function available(): int
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }
}
