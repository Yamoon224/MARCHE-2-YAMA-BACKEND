<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\UploadedFile;

/**
 * Commande payée en ligne et livrée (retrait en magasin), avec une ligne par produit fourni.
 *
 * @param  list<array{product: Product, quantity: int}>  $lines
 * @return array{order: Order, customer: User, total: int, lines: array<int, int>} `lines` : product_id => order_line_id
 */
function deliveredOrder(array $lines): array
{
    $customer = User::factory()->create();

    $response = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => array_map(fn ($l) => ['product_id' => $l['product']->id, 'quantity' => $l['quantity']], $lines),
    ])->assertCreated();

    $orderId = internalOrderId($response->json('data.id'));
    $total = $response->json('data.total_minor');

    $paymentId = startPayment($customer, $orderId)->assertCreated()->json('data.id');
    sendWebhook(webhookFor(Payment::findOrFail($paymentId)->provider_reference, 'succeeded', $total, 'evt-claim-'.$orderId))->assertOk();

    $transition = app(TransitionOrderAction::class);
    foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered] as $status) {
        $transition->execute(Order::findOrFail($orderId), $status, null);
    }

    $order = Order::with('lines')->findOrFail($orderId);

    return [
        'order' => $order,
        'customer' => $customer,
        'total' => $total,
        'lines' => $order->lines->pluck('id', 'product_id')->all(),
    ];
}

function photo(string $name = 'preuve.jpg', int $kilobytes = 100): UploadedFile
{
    return UploadedFile::fake()->image($name, 640, 480)->size($kilobytes);
}

/** Dépose une réclamation (multipart, comme le navigateur). */
function openClaim(User $customer, int $orderId, array $items, array $photos = [], ?string $description = null)
{
    return test()->actingAs($customer, 'sanctum')->post(
        '/api/v1/orders/'.orderUuid($orderId).'/claims',
        array_filter(['items' => $items, 'photos' => $photos ?: null, 'description' => $description]),
        ['Accept' => 'application/json'],
    );
}

function claimItem(int $lineId, string $issue = 'damaged', int $quantity = 1, ?string $note = null): array
{
    return array_filter(['order_line_id' => $lineId, 'issue' => $issue, 'quantity' => $quantity, 'note' => $note]);
}

function decideClaim(int $claimId, array $body, ?User $as = null)
{
    return test()->actingAs($as ?? supportAgent(), 'sanctum')->postJson("/api/v1/claims/{$claimId}/decision", $body);
}

function supportAgent(): User
{
    return User::factory()->role(Role::Support)->create();
}

/** Commande en attente de paiement (stock réservé, pas livrée) sur un produit donné. */
function awaitingPaymentOrderFor(Product $product, int $quantity): array
{
    $customer = User::factory()->create();
    $orderId = internalOrderId(test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
    ])->assertCreated()->json('data.id'));

    return ['order_id' => $orderId, 'customer' => $customer];
}
