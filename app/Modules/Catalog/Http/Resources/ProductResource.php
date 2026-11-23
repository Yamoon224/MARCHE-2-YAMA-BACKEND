<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
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
    new OA\Property(property: 'price_minor', type: 'integer', description: 'Prix en centimes (EUR)'),
    new OA\Property(property: 'image_url', type: 'string', nullable: true, description: 'URL de la couverture (compatibilité : équivaut à cover)'),
    new OA\Property(property: 'cover', type: 'string', nullable: true, description: 'URL de la couverture'),
    new OA\Property(property: 'images', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductImage')),
    new OA\Property(property: 'compare_at_price_minor', type: 'integer', nullable: true, description: 'Ancien prix (promotion) ou null'),
    new OA\Property(property: 'is_featured', type: 'boolean', description: 'Produit mis en avant (best seller)'),
    new OA\Property(property: 'vat_rate_bp', type: 'integer', description: 'Taux de TVA en points de base'),
    new OA\Property(property: 'perishable', type: 'boolean'),
    new OA\Property(property: 'track_stock', type: 'boolean', description: 'Faux : le produit se commande sans gestion de stock'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'deleted_at', type: 'string', nullable: true, description: 'Date de suppression douce (null : produit en ligne)'),
])]
#[OA\Schema(schema: 'ProductImage', required: ['id', 'url', 'position', 'is_cover'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'url', type: 'string'),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_cover', type: 'boolean'),
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
        $images = $this->resource->images;
        $cover = $this->resource->coverImage()?->url() ?? $this->resource->image_url;

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
            'image_url' => $cover,
            'cover' => $cover,
            'images' => $images->map(fn (ProductImage $i) => ['id' => $i->id, 'url' => $i->url(), 'position' => $i->position, 'is_cover' => $i->is_cover])->values()->all(),
            'compare_at_price_minor' => $this->resource->compare_at_price_minor,
            'is_featured' => $this->resource->is_featured,
            'vat_rate_bp' => $this->resource->vat_rate_bp,
            'perishable' => $this->resource->perishable,
            'track_stock' => $this->resource->track_stock,
            'is_active' => $this->resource->is_active,
            'deleted_at' => $this->resource->deleted_at?->toIso8601String(),
        ];
    }
}
