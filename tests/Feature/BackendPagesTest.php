<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

it('presents the backend on the root page', function () {
    $this->get('/')->assertOk()
        ->assertSee('Marché de Yama')
        ->assertSee('/api/documentation', false)
        ->assertSee('Modules métier')
        ->assertSee('Commandes');
});

it('serves the themed Swagger documentation without external assets', function () {
    config(['l5-swagger.defaults.generate_always' => false]);

    $html = $this->get('/api/documentation')->assertOk()->assertSee('Tous les points')->assertSee('/css/brand.css', false)->getContent();

    preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', $html, $matches);
    $external = array_filter($matches[1], fn ($url) => ! str_starts_with($url, config('app.url')) && ! str_starts_with($url, 'http://localhost') && ! str_starts_with($url, 'http://127.0.0.1'));
    expect($external)->toBe([]);
});

it('ships the logo used by both pages', function () {
    expect(public_path('brand/logo.png'))->toBeFile()->and(public_path('css/brand.css'))->toBeFile();
});

it('gives staff the customer, payments and delivery of an order, while customers see the plain order', function () {
    $ctx = readyDeliveryOrder();
    $orderId = $ctx['order']->id;

    $staff = $this->actingAs(User::factory()->role(Role::OrderOperator)->create(), 'sanctum')->getJson("/api/v1/orders/{$orderId}")->assertOk();

    $staff->assertJsonPath('data.customer.id', $ctx['customer']->id)
        ->assertJsonPath('data.customer.name', $ctx['customer']->name)
        ->assertJsonPath('data.delivery_address.neighborhood', 'Cocody')
        ->assertJsonPath('data.contact_phone', '+2250700112233')
        ->assertJsonPath('data.payments.0.status', 'succeeded')
        ->assertJsonPath('data.payments.0.refunds', [])
        ->assertJsonPath('data.delivery.status', 'pending')
        ->assertJsonStructure(['data' => ['lines', 'history', 'created_at', 'channel']]);

    $own = $this->actingAs($ctx['customer'], 'sanctum')->getJson("/api/v1/orders/{$orderId}")->assertOk();
    expect($own->json('data'))->not->toHaveKeys(['customer', 'payments', 'delivery'])
        ->and($own->json('data.lines'))->not->toBeEmpty();
});

it('returns empty payment and delivery sections for a pickup order without payment', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()->assertJsonPath('data.payments', [])->assertJsonPath('data.delivery', null);
});
