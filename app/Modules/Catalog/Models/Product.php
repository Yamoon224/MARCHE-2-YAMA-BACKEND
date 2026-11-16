<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\SaleUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SaleUnit $sale_unit
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name', 'slug', 'description', 'image_url', 'sale_unit',
        'is_weight_variable', 'price_minor', 'compare_at_price_minor', 'vat_rate_bp', 'perishable', 'is_featured', 'is_active',
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected function casts(): array
    {
        return [
            'sale_unit' => SaleUnit::class,
            'is_weight_variable' => 'boolean',
            'perishable' => 'boolean',
            'is_featured' => 'boolean',
            'compare_at_price_minor' => 'integer',
            'is_active' => 'boolean',
            'price_minor' => 'integer',
            'vat_rate_bp' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
