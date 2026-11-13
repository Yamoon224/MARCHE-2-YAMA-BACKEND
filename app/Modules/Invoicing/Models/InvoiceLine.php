<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Models;

use App\Shared\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    use Immutable;

    protected $fillable = [
        'invoice_id', 'product_id', 'sku', 'name', 'sale_unit', 'quantity',
        'unit_price_minor', 'line_total_minor', 'vat_rate_bp', 'vat_minor',
    ];
}
