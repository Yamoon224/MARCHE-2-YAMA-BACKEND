<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Recipe;
use App\Modules\Catalog\Models\RecipeItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RecipeItem', required: ['product_id', 'name', 'sale_unit', 'unit_price_minor', 'quantity'], properties: [
    new OA\Property(property: 'product_id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'sale_unit', type: 'string', enum: ['piece', 'kg', 'l']),
    new OA\Property(property: 'unit_price_minor', type: 'integer'),
    new OA\Property(property: 'quantity', type: 'integer', description: 'Unité de base : pièces, grammes ou millilitres'),
])]
#[OA\Schema(schema: 'Recipe', required: ['id', 'name', 'slug', 'total_minor', 'items'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'slug', type: 'string'),
    new OA\Property(property: 'icon', type: 'string', nullable: true),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'total_minor', type: 'integer', description: 'Prix indicatif de tous les ingrédients'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecipeItem')),
])]
/** @property Recipe $resource */
class RecipeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Le public ne voit que les produits actifs ; l'administration voit (et conserve) tous les ingrédients.
        $admin = $request->is('api/v1/catalog/*');
        $items = $this->resource->items->filter(fn (RecipeItem $i) => $admin || $i->product?->is_active)->values();

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'icon' => $this->resource->icon,
            'image_url' => $this->resource->image_url,
            'position' => $this->resource->position,
            'is_active' => $this->resource->is_active,
            'total_minor' => (int) $items->sum(fn (RecipeItem $i) => $this->lineTotal($i)),
            'items' => $items->map(fn (RecipeItem $i) => [
                'product_id' => $i->product_id,
                'name' => $i->product->name,
                'sale_unit' => $i->product->sale_unit->value,
                'unit_price_minor' => $i->product->price_minor,
                'quantity' => $i->quantity,
            ])->all(),
        ];
    }

    /** Même règle d'arrondi que le panier : prix par kg/litre, quantité en g/ml. */
    private function lineTotal(RecipeItem $item): int
    {
        $price = $item->product->price_minor;

        return $item->product->sale_unit->value === 'piece'
            ? $price * $item->quantity
            : (int) round($price * $item->quantity / 1000);
    }
}
