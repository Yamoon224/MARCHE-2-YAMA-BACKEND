<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLine extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'sku', 'name', 'sale_unit', 'quantity', 'unit_price_minor', 'line_total_minor', 'vat_rate_bp',
        'substitution', 'final_product_id', 'final_name', 'final_quantity', 'final_unit_price_minor', 'final_line_total_minor',
    ];
}
