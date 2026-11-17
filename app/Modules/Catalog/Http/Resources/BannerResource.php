<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Banner', required: ['id', 'title', 'cta_label', 'cta_url'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'title', type: 'string'),
    new OA\Property(property: 'eyebrow', type: 'string', nullable: true),
    new OA\Property(property: 'text', type: 'string', nullable: true),
    new OA\Property(property: 'cta_label', type: 'string'),
    new OA\Property(property: 'cta_url', type: 'string'),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_active', type: 'boolean'),
])]
/** @property Banner $resource */
class BannerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'eyebrow' => $this->resource->eyebrow,
            'text' => $this->resource->text,
            'cta_label' => $this->resource->cta_label,
            'cta_url' => $this->resource->cta_url,
            'image_url' => $this->resource->image_url,
            'position' => $this->resource->position,
            'is_active' => $this->resource->is_active,
        ];
    }
}
