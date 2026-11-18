<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AuditController
{
    #[OA\Get(
        path: '/api/v1/audit',
        summary: "Journal d'audit des actions du personnel (permission audit.view)",
        security: [['sanctum' => []]],
        tags: ['Audit'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'method', in: 'query', schema: new OA\Schema(type: 'string', enum: ['POST', 'PUT', 'PATCH', 'DELETE'])),
            new OA\Parameter(name: 'path', in: 'query', description: 'Fragment du chemin', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'failed', in: 'query', description: 'true : réponses en erreur (>= 400) seulement', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Entrées, les plus récentes d\'abord', content: new OA\JsonContent(ref: '#/components/schemas/AuditLogPage')),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'in:POST,PUT,PATCH,DELETE'],
        ]);

        $logs = AuditLog::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')->toString()))
            ->when($request->filled('path'), fn ($q) => $q->where('path', 'like', '%'.addcslashes($request->string('path')->toString(), '%_\\').'%'))
            ->when($request->boolean('failed'), fn ($q) => $q->where('status', '>=', 400))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')?->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')?->endOfDay()))
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return AuditLogResource::collection($logs);
    }

    #[OA\Get(
        path: '/api/v1/audit/verify',
        summary: 'Vérifier l\'intégrité de la chaîne de hachage du journal',
        security: [['sanctum' => []]],
        tags: ['Audit'],
        responses: [new OA\Response(response: 200, description: 'Résultat du contrôle', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'object', required: ['valid', 'checked'], properties: [
                new OA\Property(property: 'valid', type: 'boolean'),
                new OA\Property(property: 'checked', type: 'integer', description: 'Entrées contrôlées avec succès'),
                new OA\Property(property: 'broken_at_id', type: 'integer', nullable: true, description: 'Première entrée altérée'),
            ]),
        ]))],
    )]
    public function verify(AuditTrail $trail): JsonResponse
    {
        return response()->json(['data' => $trail->verify()]);
    }
}
