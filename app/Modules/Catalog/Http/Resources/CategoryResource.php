<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Category', required: ['id', 'name', 'slug'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'slug', type: 'string'),
    new OA\Property(property: 'icon', type: 'string', nullable: true),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'show_on_home', type: 'boolean'),
    new OA\Property(property: 'home_title', type: 'string', nullable: true),
    new OA\Property(property: 'home_subtitle', type: 'string', nullable: true),
])]
/** @property Category $resource */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'icon' => $this->resource->icon,
            'image_url' => $this->resource->image_url,
            'position' => $this->resource->position,
            'is_active' => $this->resource->is_active,
            'show_on_home' => $this->resource->show_on_home,
            'home_title' => $this->resource->home_title,
            'home_subtitle' => $this->resource->home_subtitle,
        ];
    }
}
