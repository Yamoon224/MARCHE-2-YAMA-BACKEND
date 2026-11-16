<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\RecipeResource;
use App\Modules\Catalog\Models\Recipe;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class RecipeController
{
    #[OA\Get(
        path: '/api/v1/recipes',
        summary: 'Recettes actives avec leurs ingrédients',
        tags: ['Catalogue'],
        responses: [new OA\Response(response: 200, description: 'Recettes', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Recipe'))]))],
    )]
    public function index(): AnonymousResourceCollection
    {
        return RecipeResource::collection(
            Recipe::query()->where('is_active', true)->with('items.product')->orderBy('position')->get(),
        );
    }
}
