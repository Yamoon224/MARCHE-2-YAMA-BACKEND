<?php

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;

function courierUser(string $name = 'Livreur'): User
{
    return User::factory()->role(Role::Courier)->create(['name' => $name]);
}

function dispatcher(): User
{
    return User::factory()->role(Role::OrderOperator)->create();
}

/**
 * Commande à domicile prête à partir : payée en ligne ou à la livraison, confirmée, préparée.
 *
 * @return array{order: Order, delivery: Delivery, customer: User, total: int, slot: DeliverySlot}
 */
function readyDeliveryOrder(string $method = 'mobile_money', int $price = 5000, int $quantity = 2): array
{
    $zone = DeliveryZone::factory()->create(['fee_minor' => 1500, 'free_above_minor' => null, 'min_order_minor' => 0]);
    $slot = DeliverySlot::factory()->create(['delivery_zone_id' => $zone->id, 'capacity' => 10]);
    $product = pieceProduct($price, 50);
    $customer = User::factory()->create();

    $response = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        'fulfillment' => 'delivery',
        'delivery' => [
            'zone_id' => $zone->id, 'slot_id' => $slot->id, 'phone' => '+2250700112233',
            'address' => ['neighborhood' => 'Cocody', 'landmark' => 'Portail bleu', 'latitude' => 5.35, 'longitude' => -4.0],
        ],
    ])->assertCreated();

    $orderId = $response->json('data.id');
    $total = $response->json('data.total_minor');

    if ($method === 'cash_on_delivery') {
        $paymentId = startPayment($customer, $orderId, 'cash_on_delivery')->assertCreated()->json('data.id');
        test()->actingAs(dispatcher(), 'sanctum')->postJson("/api/v1/payments/{$paymentId}/validate-cash-on-delivery")->assertOk();
    } else {
        $paymentId = startPayment($customer, $orderId)->assertCreated()->json('data.id');
        sendWebhook(webhookFor(Payment::findOrFail($paymentId)->provider_reference, 'succeeded', $total, 'evt-courier-'.$orderId))->assertOk();
    }

    $transition = app(TransitionOrderAction::class);
    foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $status) {
        $transition->execute(Order::findOrFail($orderId), $status, null);
    }

    return [
        'order' => Order::findOrFail($orderId),
        'delivery' => Delivery::where('order_id', $orderId)->firstOrFail(),
        'customer' => $customer,
        'total' => $total,
        'slot' => $slot,
    ];
}

function assignCourier(Delivery $delivery, User $courier, ?User $by = null)
{
    return test()->actingAs($by ?? dispatcher(), 'sanctum')
        ->postJson("/api/v1/delivery/deliveries/{$delivery->id}/assign", ['courier_id' => $courier->id]);
}

function courierCall(User $courier, Delivery $delivery, string $action, array $body = [])
{
    return test()->actingAs($courier, 'sanctum')->postJson("/api/v1/delivery/deliveries/{$delivery->id}/{$action}", $body);
}

/** Affecte la livraison à un livreur et la met en route. Retourne le livreur. */
function outForDelivery(Delivery $delivery): User
{
    $courier = courierUser();
    assignCourier($delivery, $courier)->assertOk();
    courierCall($courier, $delivery, 'start')->assertOk();

    return $courier;
}
