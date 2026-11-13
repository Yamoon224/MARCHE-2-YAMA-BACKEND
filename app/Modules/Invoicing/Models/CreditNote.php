<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Models;

use App\Shared\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Avoir : document légal immuable, numéroté sans trou.
 *
 * @property Carbon $issued_at
 */
class CreditNote extends Model
{
    use Immutable;

    protected $fillable = [
        'number', 'invoice_id', 'order_id', 'source_key', 'issued_at', 'currency',
        'total_minor', 'vat_minor', 'net_minor', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'total_minor' => 'integer',
            'vat_minor' => 'integer',
            'net_minor' => 'integer',
        ];
    }
}
