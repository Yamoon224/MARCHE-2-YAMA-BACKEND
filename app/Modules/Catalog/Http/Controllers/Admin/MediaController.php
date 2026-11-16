<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class MediaController
{
    #[OA\Post(path: '/api/v1/catalog/media', summary: 'Téléverser une image du catalogue', security: [['sanctum' => []]], tags: ['Catalogue (gestion)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['image'],
            properties: [new OA\Property(property: 'image', type: 'string', format: 'binary')],
            type: 'object',
        ))),
        responses: [new OA\Response(response: 201, description: 'Image enregistrée', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/MediaUpload')])), new OA\Response(response: 422, description: 'Fichier refusé')])]
    public function store(Request $request): JsonResponse
    {
        // Liste blanche de types d'image (pas de SVG : il peut embarquer du script) ; le contenu est vérifié, pas seulement l'extension.
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        $path = $request->file('image')->store('catalog', 'public');

        return response()->json(['data' => ['url' => Storage::disk('public')->url($path)]], 201);
    }
}
