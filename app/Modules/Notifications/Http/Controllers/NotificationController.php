<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'UserNotification', required: ['id', 'type', 'title', 'body', 'read', 'created_at'], properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'type', type: 'string'),
    new OA\Property(property: 'title', type: 'string'),
    new OA\Property(property: 'body', type: 'string'),
    new OA\Property(property: 'read', type: 'boolean'),
    new OA\Property(property: 'created_at', type: 'string'),
])]
class NotificationController
{
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'Mes notifications (les plus récentes en premier)',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        responses: [new OA\Response(response: 200, description: 'Notifications et nombre de non lues', content: new OA\JsonContent(required: ['data', 'meta'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/UserNotification')),
            new OA\Property(property: 'meta', required: ['unread_count'], properties: [new OA\Property(property: 'unread_count', type: 'integer')], type: 'object'),
        ]))],
    )]
    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        return response()->json([
            'data' => UserNotification::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()->map(fn (UserNotification $n) => $this->present($n))->values(),
            'meta' => ['unread_count' => UserNotification::where('user_id', $userId)->whereNull('read_at')->count()],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/notifications/{notification}/read',
        summary: 'Marquer une notification comme lue',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Notification lue', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/UserNotification')])),
            new OA\Response(response: 404, description: 'Introuvable (ou celle d\'un autre client)'),
        ],
    )]
    public function read(Request $request, int $notification): JsonResponse
    {
        $found = UserNotification::where('user_id', $this->userId($request))->findOrFail($notification);

        if ($found->read_at === null) {
            $found->update(['read_at' => now()]);
        }

        return response()->json(['data' => $this->present($found)]);
    }

    #[OA\Post(
        path: '/api/v1/notifications/read-all',
        summary: 'Tout marquer comme lu',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        responses: [new OA\Response(response: 200, description: 'Marquées lues', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', required: ['marked'], properties: [new OA\Property(property: 'marked', type: 'integer')], type: 'object'),
        ]))],
    )]
    public function readAll(Request $request): JsonResponse
    {
        $marked = UserNotification::where('user_id', $this->userId($request))->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $marked]]);
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return (int) $user->getAuthIdentifier();
    }

    /** @return array<string, mixed> */
    private function present(UserNotification $n): array
    {
        return [
            'id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body,
            'read' => $n->read_at !== null, 'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
