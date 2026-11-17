<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Resources\BannerResource;
use App\Modules\Catalog\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class BannerAdminController
{
    #[OA\Get(path: '/api/v1/catalog/banners', summary: 'Toutes les bannières (actives ou non)', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        responses: [new OA\Response(response: 200, description: 'Bannières', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Banner'))]))])]
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(Banner::query()->orderBy('position')->get());
    }

    #[OA\Post(path: '/api/v1/catalog/banners', summary: 'Créer une bannière', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BannerInput')),
        responses: [new OA\Response(response: 201, description: 'Bannière créée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Banner')])), new OA\Response(response: 422, description: 'Validation')])]
    public function store(Request $request): JsonResponse
    {
        return (new BannerResource(Banner::create($request->validate($this->rules()))))->response()->setStatusCode(201);
    }

    #[OA\Put(path: '/api/v1/catalog/banners/{banner}', summary: 'Modifier une bannière', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'banner', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BannerInput')),
        responses: [new OA\Response(response: 200, description: 'Bannière modifiée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Banner')])), new OA\Response(response: 422, description: 'Validation')])]
    public function update(Request $request, Banner $banner): BannerResource
    {
        $banner->update($request->validate($this->rules()));

        return new BannerResource($banner);
    }

    #[OA\Delete(path: '/api/v1/catalog/banners/{banner}', summary: 'Supprimer une bannière', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        parameters: [new OA\Parameter(name: 'banner', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimée')])]
    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'text' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['required', 'string', 'max:80'],
            // Lien interne (/rayons) ou absolu (https://…) : jamais javascript: ni data:.
            'cta_url' => ['required', 'string', 'max:255', 'regex:#^(/|https?://)#'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'position' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
