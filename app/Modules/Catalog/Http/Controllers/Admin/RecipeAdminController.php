<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Resources\RecipeResource;
use App\Modules\Catalog\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class RecipeAdminController
{
    #[OA\Get(path: '/api/v1/catalog/recipes', summary: 'Toutes les recettes (actives ou non)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        responses: [new OA\Response(response: 200, description: 'Recettes', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Recipe'))]))])]
    public function index(): AnonymousResourceCollection
    {
        return RecipeResource::collection(Recipe::query()->with('items.product')->orderBy('position')->get());
    }

    #[OA\Post(path: '/api/v1/catalog/recipes', summary: 'Créer une recette', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeInput')),
        responses: [new OA\Response(response: 201, description: 'Recette créée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Recipe')])), new OA\Response(response: 422, description: 'Validation')])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $recipe = DB::transaction(fn () => $this->save(new Recipe, $data));

        return (new RecipeResource($recipe))->response()->setStatusCode(201);
    }

    #[OA\Put(path: '/api/v1/catalog/recipes/{recipe}', summary: 'Modifier une recette', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeInput')),
        responses: [new OA\Response(response: 200, description: 'Recette modifiée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Recipe')])), new OA\Response(response: 422, description: 'Validation')])]
    public function update(Request $request, Recipe $recipe): RecipeResource
    {
        $data = $request->validate($this->rules($recipe));

        return new RecipeResource(DB::transaction(fn () => $this->save($recipe, $data)));
    }

    #[OA\Delete(path: '/api/v1/catalog/recipes/{recipe}', summary: 'Supprimer une recette', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimée')])]
    public function destroy(Recipe $recipe): JsonResponse
    {
        $recipe->delete();

        return response()->json(null, 204);
    }

    /** @param array<string, mixed> $data */
    private function save(Recipe $recipe, array $data): Recipe
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        $recipe->fill($data)->save();

        if ($items !== null) {
            $recipe->items()->delete();
            $recipe->items()->createMany($items);
        }

        return $recipe->load('items.product');
    }

    /** @return array<string, mixed> */
    private function rules(?Recipe $recipe = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('recipes', 'slug')->ignore($recipe?->id)],
            'icon' => ['nullable', 'string', 'max:16'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'position' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
