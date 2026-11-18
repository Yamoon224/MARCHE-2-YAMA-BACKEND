<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CategoryAdminController
{
    #[OA\Get(path: '/api/v1/catalog/categories', summary: 'Tous les rayons (actifs ou non)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        responses: [new OA\Response(response: 200, description: 'Rayons', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category'))]))])]
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->orderBy('position')->orderBy('name')->get());
    }

    #[OA\Post(path: '/api/v1/catalog/categories', summary: 'Créer un rayon', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CategoryInput')),
        responses: [new OA\Response(response: 201, description: 'Rayon créé', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Category')])), new OA\Response(response: 422, description: 'Validation')])]
    public function store(Request $request): JsonResponse
    {
        $category = Category::create($request->validate($this->rules()));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    #[OA\Put(path: '/api/v1/catalog/categories/{category}', summary: 'Modifier un rayon', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CategoryInput')),
        responses: [new OA\Response(response: 200, description: 'Rayon modifié', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Category')])), new OA\Response(response: 422, description: 'Validation')])]
    public function update(Request $request, Category $category): CategoryResource
    {
        $category->update($request->validate($this->rules($category)));

        return new CategoryResource($category);
    }

    #[OA\Delete(path: '/api/v1/catalog/categories/{category}', summary: 'Supprimer un rayon vide', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé'), new OA\Response(response: 422, description: 'Le rayon contient encore des produits')])]
    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return response()->json(['message' => 'Ce rayon contient encore des produits : déplacez-les ou désactivez le rayon.'], 422);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function rules(?Category $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'icon' => ['nullable', 'string', 'max:16'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'position' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'show_on_home' => ['boolean'],
            'home_title' => ['nullable', 'string', 'max:120'],
            'home_subtitle' => ['nullable', 'string', 'max:255'],
        ];
    }
}
