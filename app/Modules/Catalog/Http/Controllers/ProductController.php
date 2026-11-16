<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProductController
{
    #[OA\Get(
        path: '/api/v1/products',
        summary: 'Liste publique des produits actifs',
        tags: ['Catalogue'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', description: 'Slug du rayon', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'featured', in: 'query', description: 'Produits mis en avant uniquement', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'promo', in: 'query', description: 'Produits en promotion uniquement', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Numéro de page (à partir de 1)', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', description: '1 à 50 (20 par défaut)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'q', in: 'query', description: 'Recherche par nom', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Produits paginés', content: new OA\JsonContent(ref: '#/components/schemas/ProductPage'))],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->boolean('promo'), fn ($q) => $q->whereNotNull('compare_at_price_minor')->whereColumn('compare_at_price_minor', '>', 'price_minor'))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return ProductResource::collection($products);
    }

    #[OA\Get(
        path: '/api/v1/products/{slug}',
        summary: 'Fiche produit',
        tags: ['Catalogue'],
        parameters: [new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Produit', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 404, description: 'Introuvable')],
    )]
    public function show(string $slug): ProductResource
    {
        return new ProductResource(Product::where('slug', $slug)->where('is_active', true)->firstOrFail());
    }

    #[OA\Post(
        path: '/api/v1/products',
        summary: 'Créer un produit',
        security: [['sanctum' => []]],
        tags: ['Catalogue'],
        responses: [
            new OA\Response(response: 201, description: 'Produit créé', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        return (new ProductResource(Product::create($request->validated())))->response()->setStatusCode(201);
    }
}
