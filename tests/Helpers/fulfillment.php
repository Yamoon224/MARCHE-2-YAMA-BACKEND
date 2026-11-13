<?php

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Product;
use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrderAction;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;

/** Produit vendu à la pièce, approvisionné. */
function pieceProduct(int $price = 5500, int $stock = 50, string $name = 'Riz 5 kg'): Product
{
    $product = Product::factory()->create(['name' => $name, 'price_minor' => $price, 'sale_unit' => SaleUnit::Piece]);
    stockUp($product, $stock);

    return $product;
}

/** Produit vendu au kilo (quantités en grammes), approvisionné. */
function weightProduct(int $pricePerKg = 900, int $stockGrams = 100_000, string $name = 'Tomates'): Product
{
    $product = Product::factory()->byWeight()->create(['name' => $name, 'price_minor' => $pricePerKg]);
    stockUp($product, $stockGrams);

    return $product;
}

function staffUser(Role $role): User
{
    return User::factory()->role($role)->create();
}

/**
 * Passe une commande par l'API, la paie par webhook signé, puis la fait passer en préparation.
 * Retourne la commande, sa liste de préparation et le client.
 *
 * @param  list<array{product: Product, quantity: int, substitution?: string}>  $lines
 * @return array{order: Order, list: PickList, customer: User, total: int}
 */
function orderInPreparation(array $lines): array
{
    $customer = User::factory()->create();

    $response = test()->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
        'items' => array_map(fn ($l) => array_filter([
            'product_id' => $l['product']->id, 'quantity' => $l['quantity'], 'substitution' => $l['substitution'] ?? null,
        ]), $lines),
    ])->assertCreated();

    $orderId = $response->json('data.id');
    $total = $response->json('data.total_minor');

    $paymentId = startPayment($customer, $orderId)->assertCreated()->json('data.id');
    sendWebhook(webhookFor(Payment::findOrFail($paymentId)->provider_reference, 'succeeded', $total, 'evt-prep-'.$orderId))->assertOk();

    $transition = app(TransitionOrderAction::class);
    $transition->execute(Order::findOrFail($orderId), OrderStatus::Confirmed, null);
    $transition->execute(Order::findOrFail($orderId), OrderStatus::Preparing, null);

    return [
        'order' => Order::findOrFail($orderId),
        'list' => PickList::where('order_id', $orderId)->firstOrFail(),
        'customer' => $customer,
        'total' => $total,
    ];
}

function pickUrl(PickList $list, string $suffix = ''): string
{
    return "/api/v1/fulfillment/pick-lists/{$list->id}{$suffix}";
}

function lineUrl(PickList $list, int $lineId, string $action): string
{
    return pickUrl($list, "/lines/{$lineId}/{$action}");
}
