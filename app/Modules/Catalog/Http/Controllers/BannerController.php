<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\BannerResource;
use App\Modules\Catalog\Models\Banner;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class BannerController
{
    #[OA\Get(
        path: '/api/v1/banners',
        summary: 'Bannières actives de la page d\'accueil',
        tags: ['Catalogue'],
        responses: [new OA\Response(response: 200, description: 'Bannières', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Banner'))]))],
    )]
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(Banner::query()->where('is_active', true)->orderBy('position')->get());
    }
}
