<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Product', required: ['id', 'sku', 'name', 'slug', 'sale_unit', 'price_minor'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'category_id', type: 'integer'),
    new OA\Property(property: 'sku', type: 'string'),
    new OA\Property(property: 'barcode', type: 'string', nullable: true),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'slug', type: 'string'),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'sale_unit', type: 'string', enum: ['piece', 'kg', 'l']),
    new OA\Property(property: 'is_weight_variable', type: 'boolean'),
    new OA\Property(property: 'price_minor', type: 'integer', description: 'Prix en plus petite unité monétaire (F CFA)'),
    new OA\Property(property: 'vat_rate_bp', type: 'integer', description: 'Taux de TVA en points de base'),
    new OA\Property(property: 'perishable', type: 'boolean'),
    new OA\Property(property: 'is_active', type: 'boolean'),
])]
#[OA\Schema(schema: 'ProductPage', required: ['data'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
    new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]),
])]
/** @property Product $resource */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'category_id' => $this->resource->category_id,
            'sku' => $this->resource->sku,
            'barcode' => $this->resource->barcode,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'sale_unit' => $this->resource->sale_unit->value,
            'is_weight_variable' => $this->resource->is_weight_variable,
            'price_minor' => $this->resource->price_minor,
            'vat_rate_bp' => $this->resource->vat_rate_bp,
            'perishable' => $this->resource->perishable,
            'is_active' => $this->resource->is_active,
        ];
    }
}
