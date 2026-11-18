<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'OrderDetail', description: 'Commande + détails pour le personnel (client, paiements et remboursements, livraison)', allOf: [
    new OA\Schema(ref: '#/components/schemas/Order'),
    new OA\Schema(properties: [
        new OA\Property(property: 'customer', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string', nullable: true),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'payments', type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'reference', type: 'string'),
            new OA\Property(property: 'method', type: 'string'),
            new OA\Property(property: 'status', type: 'string'),
            new OA\Property(property: 'amount_minor', type: 'integer'),
            new OA\Property(property: 'currency', type: 'string'),
            new OA\Property(property: 'needs_refund', type: 'boolean'),
            new OA\Property(property: 'created_at', type: 'string', nullable: true),
            new OA\Property(property: 'refunds', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'reference', type: 'string'),
                new OA\Property(property: 'amount_minor', type: 'integer'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'kind', type: 'string'),
                new OA\Property(property: 'reason', type: 'string', nullable: true),
                new OA\Property(property: 'created_at', type: 'string', nullable: true),
            ])),
        ])),
        new OA\Property(property: 'delivery', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'status', type: 'string'),
            new OA\Property(property: 'courier_name', type: 'string', nullable: true),
            new OA\Property(property: 'cash_collected_minor', type: 'integer', nullable: true),
            new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
            new OA\Property(property: 'assigned_at', type: 'string', nullable: true),
            new OA\Property(property: 'delivered_at', type: 'string', nullable: true),
        ]),
    ]),
])]
/** @property Order $resource */
class OrderDetailResource extends OrderResource
{
    /**
     * @param  array<string, mixed>  $sections  Sections ajoutées par les autres modules (payments, delivery…)
     * @param  array{id: int, name: string, email: string|null, phone: string|null}|null  $customer
     */
    public function __construct(Order $order, private readonly array $sections, private readonly ?array $customer)
    {
        parent::__construct($order);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), ['customer' => $this->customer, 'payments' => [], 'delivery' => null], $this->sections);
    }
}
