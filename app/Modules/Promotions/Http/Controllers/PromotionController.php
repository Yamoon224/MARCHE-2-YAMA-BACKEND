<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Enums\PromotionState;
use App\Modules\Promotions\Http\Requests\PromotionRequest;
use App\Modules\Promotions\Http\Requests\ValidatePromotionRequest;
use App\Modules\Promotions\Http\Resources\PromotionResource;
use App\Modules\Promotions\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PromotionController
{
    #[OA\Get(
        path: '/api/v1/promotions',
        summary: 'Codes promotionnels (permission promotions.manage)',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'scheduled', 'expired', 'exhausted', 'disabled'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Codes paginés', content: new OA\JsonContent(ref: '#/components/schemas/PromotionPage'))],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = trim($request->string('q')->toString());
        $state = PromotionState::tryFrom($request->string('state')->toString());
        $now = now();
        $notExhausted = '(max_uses IS NULL OR used_count < max_uses)';

        $query = Promotion::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('code', 'like', '%'.addcslashes(strtoupper($q), '%_\\').'%')
                ->orWhere('description', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($state !== null, fn ($query) => match ($state) {
                PromotionState::Disabled => $query->where('is_active', false),
                PromotionState::Exhausted => $query->where('is_active', true)->whereNotNull('max_uses')->whereColumn('used_count', '>=', 'max_uses'),
                PromotionState::Scheduled => $query->where('is_active', true)->where('starts_at', '>', $now)->whereRaw($notExhausted),
                PromotionState::Expired => $query->where('is_active', true)->where('ends_at', '<', $now)
                    ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->whereRaw($notExhausted),
                default => $query->where('is_active', true)
                    ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                    ->whereRaw($notExhausted),
            })
            ->orderByDesc('id');

        return PromotionResource::collection($query->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    #[OA\Post(
        path: '/api/v1/promotions',
        summary: 'Créer un code promotionnel',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code', 'type', 'value'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'BIENVENUE10'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'type', type: 'string', enum: ['percent', 'fixed']),
                new OA\Property(property: 'value', type: 'integer'),
                new OA\Property(property: 'min_order_minor', type: 'integer'),
                new OA\Property(property: 'max_uses', type: 'integer', nullable: true),
                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Code créé', content: new OA\JsonContent(ref: '#/components/schemas/PromotionResponse')),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function store(PromotionRequest $request): JsonResponse
    {
        $promotion = Promotion::create($request->validated());

        return (new PromotionResource($promotion->refresh()))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/promotions/{promotion}',
        summary: "Détail d'un code",
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Code', content: new OA\JsonContent(ref: '#/components/schemas/PromotionResponse')),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($promotion);
    }

    #[OA\Put(
        path: '/api/v1/promotions/{promotion}',
        summary: 'Modifier un code',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'type', type: 'string', enum: ['percent', 'fixed']),
            new OA\Property(property: 'value', type: 'integer'),
            new OA\Property(property: 'min_order_minor', type: 'integer'),
            new OA\Property(property: 'max_uses', type: 'integer', nullable: true),
            new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'is_active', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Code mis à jour', content: new OA\JsonContent(ref: '#/components/schemas/PromotionResponse')),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function update(PromotionRequest $request, Promotion $promotion): PromotionResource
    {
        $promotion->update($request->validated());

        return new PromotionResource($promotion->refresh());
    }

    #[OA\Delete(
        path: '/api/v1/promotions/{promotion}',
        summary: 'Supprimer un code jamais utilisé',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 422, description: 'Code déjà utilisé : le désactiver plutôt'),
        ],
    )]
    public function destroy(Promotion $promotion): JsonResponse
    {
        if ($promotion->used_count > 0) {
            throw ValidationException::withMessages(['promotion' => ['Ce code a déjà été utilisé : désactivez-le plutôt que de le supprimer.']]);
        }

        $promotion->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/promotions/{promotion}/activate',
        summary: 'Activer un code',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Code', content: new OA\JsonContent(ref: '#/components/schemas/PromotionResponse'))],
    )]
    public function activate(Promotion $promotion): PromotionResource
    {
        $promotion->update(['is_active' => true]);

        return new PromotionResource($promotion);
    }

    #[OA\Post(
        path: '/api/v1/promotions/{promotion}/deactivate',
        summary: 'Désactiver un code',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        parameters: [new OA\Parameter(name: 'promotion', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Code', content: new OA\JsonContent(ref: '#/components/schemas/PromotionResponse'))],
    )]
    public function deactivate(Promotion $promotion): PromotionResource
    {
        $promotion->update(['is_active' => false]);

        return new PromotionResource($promotion);
    }

    #[OA\Post(
        path: '/api/v1/promotions/validate',
        summary: "Contrôler un code pour un sous-total et obtenir la remise (n'incrémente pas l'usage)",
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code', 'subtotal'],
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'subtotal', type: 'integer', description: 'Sous-total du panier en centimes'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Code valable', content: new OA\JsonContent(required: ['data'], properties: [
                new OA\Property(property: 'data', type: 'object', required: ['code', 'type', 'discount_minor', 'total_after_discount_minor'], properties: [
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'discount_minor', type: 'integer'),
                    new OA\Property(property: 'total_after_discount_minor', type: 'integer'),
                ]),
            ])),
            new OA\Response(response: 422, description: 'Code inconnu, inactif, expiré, épuisé ou minimum non atteint'),
        ],
    )]
    public function validateCode(ValidatePromotionRequest $request): JsonResponse
    {
        $promotion = Promotion::where('code', strtoupper(trim($request->string('code')->toString())))->first();
        $subtotal = $request->integer('subtotal');

        if ($promotion === null) {
            throw ValidationException::withMessages(['code' => ["Ce code promotionnel n'existe pas."]]);
        }

        $state = $promotion->state();
        if ($state !== PromotionState::Active) {
            throw ValidationException::withMessages(['code' => [$state->reason()]]);
        }

        if ($subtotal < $promotion->min_order_minor) {
            throw ValidationException::withMessages(['code' => ["Le montant minimum de commande pour ce code n'est pas atteint."]]);
        }

        $discount = $promotion->discountFor($subtotal);

        return response()->json(['data' => [
            'code' => $promotion->code,
            'type' => $promotion->type->value,
            'discount_minor' => $discount,
            'total_after_discount_minor' => $subtotal - $discount,
        ]]);
    }
}
