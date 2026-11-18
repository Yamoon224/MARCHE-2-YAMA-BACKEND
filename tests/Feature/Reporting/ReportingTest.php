<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Claims\Enums\ClaimStatus;
use App\Modules\Claims\Models\Claim;
use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliverySlot;
use App\Modules\Delivery\Models\SlotBooking;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

function saleOn(string $date, OrderStatus $status, Product $product, int $quantity, int $lineTotal, ?int $finalTotal = null): Order
{
    $order = Order::factory()->status($status)->create([
        'total_minor' => $lineTotal, 'subtotal_minor' => $lineTotal, 'final_total_minor' => $finalTotal, 'created_at' => $date.' 12:00:00',
    ]);
    $order->lines()->create([
        'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'sale_unit' => 'piece',
        'quantity' => $quantity, 'unit_price_minor' => intdiv($lineTotal, $quantity), 'line_total_minor' => $lineTotal,
    ]);

    return $order;
}

function reportViewer(Role $role = Role::Accountant): void
{
    test()->actingAs(User::factory()->role($role)->create(), 'sanctum');
}

beforeEach(function () {
    $this->fruits = Category::factory()->create(['name' => 'Fruits']);
    $this->boissons = Category::factory()->create(['name' => 'Boissons']);
    $this->pomme = Product::factory()->create(['name' => 'Pomme', 'category_id' => $this->fruits->id]);
    $this->jus = Product::factory()->create(['name' => 'Jus', 'category_id' => $this->boissons->id]);

    saleOn('2026-11-02', OrderStatus::Delivered, $this->pomme, 4, 1000);
    saleOn('2026-11-02', OrderStatus::Completed, $this->jus, 2, 3000, 2500);   // total final après préparation
    saleOn('2026-11-04', OrderStatus::Paid, $this->pomme, 1, 500);
    saleOn('2026-11-04', OrderStatus::Cancelled, $this->jus, 1, 9999);          // ni vente ni CA
    saleOn('2026-10-01', OrderStatus::Delivered, $this->pomme, 1, 777);         // hors période
});

it('builds the dashboard with revenue, basket, statuses, daily series and top products', function () {
    reportViewer();

    $data = $this->getJson('/api/v1/reports/dashboard?from=2026-11-01&to=2026-11-05')->assertOk()->json('data');

    expect($data['revenue_minor'])->toBe(1000 + 2500 + 500)
        ->and($data['orders_count'])->toBe(3)
        ->and($data['average_basket_minor'])->toBe(1333)
        ->and($data['currency'])->toBe('EUR')
        ->and($data['period'])->toBe(['from' => '2026-11-01', 'to' => '2026-11-05'])
        ->and(collect($data['orders_by_status'])->firstWhere('status', 'cancelled')['count'])->toBe(1)
        ->and($data['sales_by_day'])->toHaveCount(5)
        ->and($data['sales_by_day'][1])->toBe(['date' => '2026-11-02', 'orders_count' => 2, 'revenue_minor' => 3500])
        ->and($data['sales_by_day'][0]['revenue_minor'])->toBe(0)
        ->and($data['top_products'][0]['name'])->toBe('Jus')
        ->and($data['top_products'][0]['revenue_minor'])->toBe(3000)
        ->and($data['alerts'])->toHaveKeys(['expiring_lots', 'out_of_stock_products', 'open_claims', 'late_deliveries']);
});

it('has a sensible empty dashboard', function () {
    reportViewer();

    $data = $this->getJson('/api/v1/reports/dashboard?from=2020-01-01&to=2020-01-03')->assertOk()->json('data');

    expect($data['revenue_minor'])->toBe(0)->and($data['average_basket_minor'])->toBe(0)->and($data['top_products'])->toBe([])->and($data['sales_by_day'])->toHaveCount(3);
});

it('defaults to the last 30 days and refuses bad periods', function () {
    reportViewer();

    $this->getJson('/api/v1/reports/dashboard')->assertOk()->assertJsonCount(30, 'data.sales_by_day');
    $this->getJson('/api/v1/reports/dashboard?from=2026-11-05&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('to');
    $this->getJson('/api/v1/reports/dashboard?from=2020-01-01&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('from');
    $this->getJson('/api/v1/reports/dashboard?from=hier')->assertUnprocessable();
});

it('raises operational alerts through the module contracts', function () {
    $claimOrder = Order::factory()->status(OrderStatus::Claim)->create();
    Claim::create(['order_id' => $claimOrder->id, 'user_id' => User::factory()->create()->id, 'status' => ClaimStatus::Open]);
    Claim::create(['order_id' => Order::factory()->status(OrderStatus::Claim)->create()->id, 'user_id' => User::factory()->create()->id, 'status' => ClaimStatus::Accepted]);

    $slot = DeliverySlot::factory()->create(['starts_at' => now()->subHours(5), 'ends_at' => now()->subHours(3)]);
    $late = Order::factory()->status(OrderStatus::Assigned)->create();
    SlotBooking::create(['delivery_slot_id' => $slot->id, 'order_id' => $late->id]);
    Delivery::create(['order_id' => $late->id, 'status' => DeliveryStatus::Assigned, 'confirmation_code' => '1234']);
    $done = Order::factory()->status(OrderStatus::Delivered)->create();
    SlotBooking::create(['delivery_slot_id' => $slot->id, 'order_id' => $done->id]);
    Delivery::create(['order_id' => $done->id, 'status' => DeliveryStatus::Delivered, 'confirmation_code' => '1234']);

    stockUp($this->pomme, 10, now()->addDays(3)->toDateString());
    stockUp($this->jus, 10, now()->addDays(60)->toDateString());
    stockUp(Product::factory()->create(), 0);
    reportViewer();

    $alerts = $this->getJson('/api/v1/reports/dashboard')->assertOk()->json('data.alerts');

    expect($alerts['open_claims'])->toBe(1)
        ->and($alerts['late_deliveries'])->toBe(1)
        ->and($alerts['expiring_lots'])->toBe(1)
        ->and($alerts['out_of_stock_products'])->toBe(1);
});

it('groups sales by day, product and category', function () {
    reportViewer();
    $query = 'from=2026-11-01&to=2026-11-05';

    $byDay = $this->getJson("/api/v1/reports/sales?{$query}")->assertOk()->assertJsonPath('data.group', 'day')->assertJsonPath('data.totals.revenue_minor', 4000)->json('data.rows');
    expect($byDay)->toHaveCount(5);

    $byProduct = $this->getJson("/api/v1/reports/sales?{$query}&group=product")->assertOk()->json('data.rows');
    expect($byProduct[0]['label'])->toBe('Jus')->and($byProduct[0]['revenue_minor'])->toBe(3000)
        ->and($byProduct[1]['label'])->toBe('Pomme')->and($byProduct[1]['quantity'])->toBe(5);

    $byCategory = $this->getJson("/api/v1/reports/sales?{$query}&group=category")->assertOk()->json('data.rows');
    expect(array_column($byCategory, 'label'))->toBe(['Boissons', 'Fruits'])
        ->and($byCategory[1]['revenue_minor'])->toBe(1500);

    $this->getJson("/api/v1/reports/sales?{$query}&group=client")->assertUnprocessable();
});

it('exports sales as a CSV download', function () {
    reportViewer();

    $response = $this->get('/api/v1/reports/sales?from=2026-11-01&to=2026-11-05&group=product&format=csv', ['Accept' => 'application/json']);

    $response->assertOk()->assertDownload('ventes-product-2026-11-01-2026-11-05.csv');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Produit;Commandes;Quantité')->toContain('Pomme;;5;15,00');
});

it('restricts reports to accountant, order operator and admin', function () {
    foreach ([Role::Accountant, Role::OrderOperator, Role::Admin] as $role) {
        reportViewer($role);
        $this->getJson('/api/v1/reports/dashboard')->assertOk();
        $this->getJson('/api/v1/reports/sales')->assertOk();
        asGuest();
    }

    foreach ([Role::Customer, Role::Support, Role::Marketing, Role::Picker, Role::Courier] as $role) {
        reportViewer($role);
        $this->getJson('/api/v1/reports/dashboard')->assertForbidden();
        $this->getJson('/api/v1/reports/sales')->assertForbidden();
        asGuest();
    }

    $this->getJson('/api/v1/reports/dashboard')->assertUnauthorized();
});
