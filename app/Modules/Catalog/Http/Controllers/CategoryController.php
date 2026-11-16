<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CategoryController
{
    #[OA\Get(
        path: '/api/v1/categories',
        summary: 'Rayons actifs, dans l\'ordre d\'affichage',
        tags: ['Catalogue'],
        responses: [new OA\Response(response: 200, description: 'Rayons', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category'))]))],
    )]
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(),
        );
    }
}
