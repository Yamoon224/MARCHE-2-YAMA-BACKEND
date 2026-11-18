<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ProductAdminController
{
    public function __construct(private readonly ProductImageService $images) {}

    #[OA\Get(path: '/api/v1/catalog/products', summary: 'Tous les produits (actifs ou non)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'trashed', in: 'query', description: 'with : y compris supprimés ; only : uniquement les supprimés', schema: new OA\Schema(type: 'string', enum: ['with', 'only'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: '1 à 200 (30 par défaut)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Produits paginés', content: new OA\JsonContent(ref: '#/components/schemas/ProductPage'))])]
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['trashed' => ['nullable', Rule::in(['with', 'only'])]]);

        return ProductResource::collection(
            Product::query()
                ->with('images')
                ->when($request->input('trashed') === 'with', fn ($q) => $q->withTrashed())
                ->when($request->input('trashed') === 'only', fn ($q) => $q->onlyTrashed())
                ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%')))
                ->orderBy('name')
                ->paginate(min(max($request->integer('per_page', 30), 1), 200)),
        );
    }

    #[OA\Post(path: '/api/v1/catalog/products', summary: 'Créer un produit', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductUpdate')),
        responses: [new OA\Response(response: 201, description: 'Produit créé', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 422, description: 'Validation')])]
    public function store(StoreProductRequest $request): JsonResponse
    {
        return (new ProductResource($this->images->create($request->validated())))->response()->setStatusCode(201);
    }

    #[OA\Get(path: '/api/v1/catalog/products/{product}', summary: "Détails d'un produit (même supprimé) avec sa galerie", security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Produit', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 404, description: 'Introuvable')])]
    public function show(int $product): ProductResource
    {
        return new ProductResource(Product::withTrashed()->with('images')->findOrFail($product));
    }

    #[OA\Put(path: '/api/v1/catalog/products/{product}', summary: 'Modifier un produit (prix, promotion, visibilité…)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductUpdate')),
        responses: [new OA\Response(response: 200, description: 'Produit modifié', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 422, description: 'Validation')])]
    public function update(Request $request, Product $product): ProductResource
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'sku' => ['sometimes', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:20'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'sale_unit' => ['sometimes', Rule::enum(SaleUnit::class)],
            'is_weight_variable' => ['boolean'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0'],
            'vat_rate_bp' => ['integer', 'min:0', 'max:10000'],
            'perishable' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        return new ProductResource($this->images->update($product, $data));
    }

    #[OA\Delete(path: '/api/v1/catalog/products/{product}', summary: 'Supprimer un produit (suppression douce, restaurable)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé : il disparaît du catalogue, les commandes passées le conservent'), new OA\Response(response: 404, description: 'Introuvable')])]
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(path: '/api/v1/catalog/products/{product}/restore', summary: 'Restaurer un produit supprimé', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Produit restauré', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 404, description: 'Introuvable')])]
    public function restore(int $product): ProductResource
    {
        $model = Product::onlyTrashed()->findOrFail($product);
        $model->restore();

        return new ProductResource($model->load('images'));
    }

    #[OA\Post(path: '/api/v1/catalog/products/{product}/images', summary: 'Ajouter des images (10 au plus par produit ; la première devient la couverture)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['images'],
            properties: [new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string', format: 'binary'))],
            type: 'object',
        ))),
        responses: [new OA\Response(response: 201, description: 'Produit avec sa galerie', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 422, description: "Fichier refusé ou trop d'images")])]
    public function addImages(Request $request, Product $product): JsonResponse
    {
        // Liste blanche de types d'image (pas de SVG : il peut embarquer du script) ; le contenu est vérifié, pas seulement l'extension.
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.ProductImageService::MAX_IMAGES],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        /** @var list<UploadedFile> $files */
        $files = array_values($request->file('images'));

        return (new ProductResource($this->images->add($product, $files)))->response()->setStatusCode(201);
    }

    #[OA\Put(path: '/api/v1/catalog/products/{product}/images/order', summary: 'Réordonner la galerie', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['ids'], properties: [
            new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Toutes les images du produit, dans le nouvel ordre'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Produit avec sa galerie', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 422, description: 'Liste incomplète')])]
    public function reorderImages(Request $request, Product $product): ProductResource
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']]);

        return new ProductResource($this->images->reorder($product, array_map('intval', $data['ids'])));
    }

    #[OA\Post(path: '/api/v1/catalog/products/{product}/images/{image}/cover', summary: "Choisir l'image de couverture", security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Produit avec sa galerie', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 404, description: 'Image introuvable pour ce produit')])]
    public function setCover(Product $product, int $image): ProductResource
    {
        return new ProductResource($this->images->setCover($product, $this->imageOf($product, $image)));
    }

    #[OA\Delete(path: '/api/v1/catalog/products/{product}/images/{image}', summary: 'Supprimer une image (le fichier est effacé)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Produit avec sa galerie', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])), new OA\Response(response: 404, description: 'Image introuvable pour ce produit')])]
    public function removeImage(Product $product, int $image): ProductResource
    {
        return new ProductResource($this->images->remove($product, $this->imageOf($product, $image)));
    }

    private function imageOf(Product $product, int $image): ProductImage
    {
        return $product->images()->whereKey($image)->firstOrFail();
    }
}
