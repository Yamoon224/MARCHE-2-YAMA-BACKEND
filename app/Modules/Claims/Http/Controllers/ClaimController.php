<?php

declare(strict_types=1);

namespace App\Modules\Claims\Http\Controllers;

use App\Modules\Claims\Actions\DecideClaimAction;
use App\Modules\Claims\Actions\OpenClaimAction;
use App\Modules\Claims\Enums\ClaimResolution;
use App\Modules\Claims\Enums\CostBearer;
use App\Modules\Claims\Exceptions\ClaimNotAllowed;
use App\Modules\Claims\Http\Requests\DecideClaimRequest;
use App\Modules\Claims\Http\Requests\OpenClaimRequest;
use App\Modules\Claims\Http\Resources\ClaimResource;
use App\Modules\Claims\Models\Claim;
use App\Modules\Claims\Models\ClaimPhoto;
use App\Modules\Claims\Services\QualityStats;
use App\Modules\Identity\Contracts\PermissionHolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimController
{
    #[OA\Post(
        path: '/api/v1/orders/{orderId}/claims',
        summary: 'Déclarer un produit manquant, abîmé, périmé ou non conforme (photos jointes)',
        security: [['sanctum' => []]],
        tags: ['Réclamations'],
        parameters: [new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['items'],
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    required: ['order_line_id', 'issue', 'quantity'],
                    properties: [
                        new OA\Property(property: 'order_line_id', type: 'integer'),
                        new OA\Property(property: 'issue', type: 'string', enum: ['missing', 'damaged', 'expired', 'non_conforming']),
                        new OA\Property(property: 'quantity', type: 'integer', description: 'Pièces, grammes ou millilitres'),
                        new OA\Property(property: 'note', type: 'string', nullable: true),
                    ],
                )),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'photos', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
            ],
        ))),
        responses: [
            new OA\Response(response: 201, description: 'Réclamation enregistrée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Claim')])),
            new OA\Response(response: 404, description: 'Commande introuvable'),
            new OA\Response(response: 422, description: 'Délai dépassé, quantité ou photo invalide, réclamation déjà déposée'),
        ],
    )]
    public function store(OpenClaimRequest $request, int $orderId, OpenClaimAction $action): JsonResponse
    {
        $user = $this->user($request);

        try {
            /** @var list<array{order_line_id: int, issue: string, quantity: int, note?: string|null}> $items */
            $items = $request->validated('items');
            $claim = $action->execute(
                (int) $user->getAuthIdentifier(), $orderId, $items, array_values($request->file('photos', [])), $request->input('description'),
            );
        } catch (ClaimNotAllowed $e) {
            abort_if($e->getMessage() === ClaimNotAllowed::orderNotFound()->getMessage(), 404, $e->getMessage());
            throw ValidationException::withMessages(['claim' => [$e->getMessage()]]);
        }

        return (new ClaimResource($claim))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/claims',
        summary: 'Mes réclamations (toutes pour le personnel habilité)',
        security: [['sanctum' => []]],
        tags: ['Réclamations'],
        parameters: [new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'accepted', 'rejected']))],
        responses: [new OA\Response(response: 200, description: 'Réclamations', content: new OA\JsonContent(required: ['data'], properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Claim')),
        ]))],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $status = $request->string('status')->toString();

        $claims = Claim::with(['items', 'photos'])
            ->when(! $user->hasPermission('claims.decide'), fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("status = 'open' DESC")->orderBy('created_at')
            ->limit(100)->get();

        return ClaimResource::collection($claims);
    }

    #[OA\Get(
        path: '/api/v1/claims/{claim}',
        summary: 'Détail d\'une réclamation',
        security: [['sanctum' => []]],
        tags: ['Réclamations'],
        parameters: [new OA\Parameter(name: 'claim', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Réclamation', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Claim')])),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(Request $request, Claim $claim): ClaimResource
    {
        $this->authorizeView($request, $claim);

        return new ClaimResource($claim);
    }

    #[OA\Get(
        path: '/api/v1/claims/{claim}/photos/{photo}',
        summary: 'Photo d\'une réclamation (réservée à son auteur et au personnel habilité)',
        security: [['sanctum' => []]],
        tags: ['Réclamations'],
        parameters: [
            new OA\Parameter(name: 'claim', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Image'), new OA\Response(response: 404, description: 'Introuvable')],
    )]
    public function photo(Request $request, Claim $claim, ClaimPhoto $photo): StreamedResponse
    {
        $this->authorizeView($request, $claim);

        $disk = Storage::disk((string) config('claims.disk'));
        abort_unless($disk->exists($photo->path), 404);

        return $disk->response($photo->path, null, [
            'Content-Type' => $photo->mime,
            // Jamais interprété comme autre chose qu'une image, jamais mis en cache partagé.
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline',
        ]);
    }

    #[OA\Post(
        path: '/api/v1/claims/{claim}/decision',
        summary: 'Décider une réclamation : rejet motivé, ou remboursement / nouvel envoi avec imputation du coût',
        security: [['sanctum' => []]],
        tags: ['Réclamations'],
        parameters: [new OA\Parameter(name: 'claim', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['decision', 'note'],
            properties: [
                new OA\Property(property: 'decision', type: 'string', enum: ['accept', 'reject']),
                new OA\Property(property: 'note', type: 'string', description: 'Motif de la décision (obligatoire, même pour un rejet)'),
                new OA\Property(property: 'resolution', type: 'string', enum: ['refund', 'redelivery'], nullable: true),
                new OA\Property(property: 'cost_bearer', type: 'string', enum: ['supplier', 'preparation', 'delivery', 'store'], nullable: true),
                new OA\Property(property: 'amount_minor', type: 'integer', nullable: true, description: 'Montant remboursé, au plus la valeur des articles réclamés'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Réclamation décidée', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Claim')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Déjà décidée, montant excessif ou remboursement impossible'),
        ],
    )]
    public function decide(DecideClaimRequest $request, Claim $claim, DecideClaimAction $action): ClaimResource
    {
        $user = $this->user($request);
        $accept = $request->input('decision') === 'accept';

        try {
            return new ClaimResource($action->execute(
                $claim,
                (int) $user->getAuthIdentifier(),
                $accept,
                $request->string('note')->toString(),
                $accept ? ClaimResolution::from($request->string('resolution')->toString()) : null,
                $accept ? CostBearer::from($request->string('cost_bearer')->toString()) : null,
                $request->filled('amount_minor') ? $request->integer('amount_minor') : null,
            ));
        } catch (ClaimNotAllowed $e) {
            throw ValidationException::withMessages(['claim' => [$e->getMessage()]]);
        }
    }

    #[OA\Get(
        path: '/api/v1/claims/quality-stats',
        summary: 'Statistiques de qualité : produits les plus réclamés, causes, coûts par imputation',
        security: [['sanctum' => []]],
        tags: ['Réclamations'],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques', content: new OA\JsonContent(required: ['claims_total', 'accepted', 'rejected', 'open', 'refunded_minor', 'by_product', 'by_issue', 'by_cost_bearer'], properties: [
                new OA\Property(property: 'claims_total', type: 'integer'),
                new OA\Property(property: 'accepted', type: 'integer'),
                new OA\Property(property: 'rejected', type: 'integer'),
                new OA\Property(property: 'open', type: 'integer'),
                new OA\Property(property: 'refunded_minor', type: 'integer'),
                new OA\Property(property: 'by_product', type: 'array', items: new OA\Items(required: ['product_id', 'name', 'claims', 'units'], properties: [
                    new OA\Property(property: 'product_id', type: 'integer'), new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'claims', type: 'integer'), new OA\Property(property: 'units', type: 'integer'),
                ])),
                new OA\Property(property: 'by_issue', type: 'array', items: new OA\Items(required: ['issue', 'items'], properties: [
                    new OA\Property(property: 'issue', type: 'string'), new OA\Property(property: 'items', type: 'integer'),
                ])),
                new OA\Property(property: 'by_cost_bearer', type: 'array', items: new OA\Items(required: ['cost_bearer', 'claims', 'refunded_minor'], properties: [
                    new OA\Property(property: 'cost_bearer', type: 'string'), new OA\Property(property: 'claims', type: 'integer'),
                    new OA\Property(property: 'refunded_minor', type: 'integer'),
                ])),
            ])),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function stats(QualityStats $stats): JsonResponse
    {
        return response()->json($stats->compute());
    }

    private function user(Request $request): PermissionHolder
    {
        $user = $request->user();
        abort_unless($user instanceof PermissionHolder, 401);

        return $user;
    }

    /** 404 (et non 403) pour la réclamation d'un autre client : pas d'énumération possible. */
    private function authorizeView(Request $request, Claim $claim): void
    {
        $user = $this->user($request);

        abort_unless($claim->user_id === $user->getAuthIdentifier() || $user->hasPermission('claims.decide'), 404);
    }
}
