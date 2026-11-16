<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ProductAdminController
{
    #[OA\Get(path: '/api/v1/catalog/products', summary: 'Tous les produits (actifs ou non)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: '1 à 200 (30 par défaut)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Produits paginés', content: new OA\JsonContent(ref: '#/components/schemas/ProductPage'))])]
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%')))
                ->orderBy('name')
                ->paginate(min(max($request->integer('per_page', 30), 1), 200)),
        );
    }

    #[OA\Put(path: '/api/v1/catalog/products/{product}', summary: 'Modifier un produit (prix, promotion, image, visibilité)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductUpdate')),
        responses: [new OA\Response(response: 200, description: 'Produit modifié', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 422, description: 'Validation')])]
    public function update(Request $request, Product $product): ProductResource
    {
        $product->update($request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
        ]));

        return new ProductResource($product);
    }
}
