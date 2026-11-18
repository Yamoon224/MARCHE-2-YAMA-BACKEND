<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AuditLog', required: ['id', 'user_id', 'method', 'path', 'status', 'payload', 'created_at', 'hash'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'user_id', type: 'integer'),
    new OA\Property(property: 'method', type: 'string'),
    new OA\Property(property: 'path', type: 'string'),
    new OA\Property(property: 'status', type: 'integer'),
    new OA\Property(property: 'ip', type: 'string', nullable: true),
    new OA\Property(property: 'payload', type: 'object', additionalProperties: true, description: 'Données envoyées, secrets masqués'),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'hash', type: 'string'),
])]
#[OA\Schema(schema: 'AuditLogPage', required: ['data', 'meta'], properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLog')),
    new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]),
])]
/** @property AuditLog $resource */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $log = $this->resource;
        $payload = json_decode($log->payload, true);

        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'method' => $log->method,
            'path' => $log->path,
            'status' => $log->status,
            'ip' => $log->ip,
            'payload' => is_array($payload) && $payload !== [] ? $payload : (object) [],
            'created_at' => $log->created_at->toIso8601String(),
            'hash' => $log->hash,
        ];
    }
}
