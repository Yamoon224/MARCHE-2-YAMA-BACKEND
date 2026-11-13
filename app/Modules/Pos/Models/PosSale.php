<?php

declare(strict_types=1);

namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $created_at
 * @property list<array{name: string, quantity: int, sale_unit: string, unit_price_minor: int, line_total_minor: int}> $lines
 */
class PosSale extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pos_session_id', 'order_id', 'order_number', 'tender', 'total_minor', 'tendered_minor', 'change_minor', 'lines', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'total_minor' => 'integer', 'tendered_minor' => 'integer', 'change_minor' => 'integer',
            'lines' => 'array', 'created_at' => 'datetime',
        ];
    }
}
