<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Invoicing\Actions\GenerateInvoiceAction;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;

it('issues an invoice when the order is paid, with VAT extracted from the gross price', function () {
    $ctx = awaitingPaymentOrder(2, 5900, 1800); // 11 800 TTC dont 1 800 de TVA
    payOrder($ctx);

    $this->actingAs($ctx['customer'], 'sanctum')->getJson('/api/v1/orders/'.orderUuid($ctx['orderId']).'/invoice')
        ->assertOk()
        ->assertJsonPath('data.number', sprintf('FAC-%d-000001', now()->year))
        ->assertJsonPath('data.total_minor', 11800)
        ->assertJsonPath('data.vat_minor', 1800)
        ->assertJsonPath('data.net_minor', 10000)
        ->assertJsonPath('data.lines.0.vat_rate_bp', 1800)
        ->assertJsonPath('data.lines.0.vat_minor', 1800);
});

it('does not issue an invoice before payment', function () {
    $ctx = awaitingPaymentOrder();

    expect(Invoice::count())->toBe(0);
    $this->actingAs($ctx['customer'], 'sanctum')->getJson('/api/v1/orders/'.orderUuid($ctx['orderId']).'/invoice')->assertNotFound();
});

it('numbers invoices sequentially without gaps', function () {
    foreach (['evt-a', 'evt-b', 'evt-c'] as $event) {
        payOrder(awaitingPaymentOrder(), $event);
    }

    expect(Invoice::orderBy('id')->pluck('number')->all())->toBe([
        sprintf('FAC-%d-000001', now()->year),
        sprintf('FAC-%d-000002', now()->year),
        sprintf('FAC-%d-000003', now()->year),
    ]);
});

it('restarts the numbering for a new fiscal year', function () {
    $this->travelTo(now()->setDate(2026, 12, 31));
    payOrder(awaitingPaymentOrder(), 'evt-y1');

    $this->travelTo(now()->setDate(2027, 1, 2));
    payOrder(awaitingPaymentOrder(), 'evt-y2');

    expect(Invoice::orderBy('id')->pluck('number')->all())->toBe(['FAC-2026-000001', 'FAC-2027-000001']);
});

it('is idempotent: one invoice per order, whatever the replays', function () {
    $ctx = awaitingPaymentOrder();
    payOrder($ctx);

    $first = Invoice::firstOrFail();
    $again = app(GenerateInvoiceAction::class)->execute($ctx['orderId']);
    app(GenerateInvoiceAction::class)->execute($ctx['orderId']);

    expect($again->id)->toBe($first->id)->and(Invoice::count())->toBe(1);
});

it('keeps the invoice total equal to the order total', function () {
    $ctx = awaitingPaymentOrder(3, 1234, 1800);
    payOrder($ctx);

    $invoice = Invoice::with('lines')->firstOrFail();
    expect($invoice->total_minor)->toBe($ctx['total'])
        ->and($invoice->net_minor + $invoice->vat_minor)->toBe($invoice->total_minor)
        ->and($invoice->lines->sum('line_total_minor'))->toBe($invoice->total_minor);
});

it('shows an invoice only to its owner and authorised staff', function () {
    $ctx = awaitingPaymentOrder();
    payOrder($ctx);
    $url = '/api/v1/orders/'.orderUuid($ctx['orderId']).'/invoice';

    asGuest();
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertNotFound();
    $this->actingAs(User::factory()->role(Role::Courier)->create(), 'sanctum')->getJson($url)->assertNotFound();
    $this->actingAs(User::factory()->role(Role::Accountant)->create(), 'sanctum')->getJson($url)->assertOk();
    $this->actingAs($ctx['customer'], 'sanctum')->getJson($url)->assertOk();
});

it('cannot be modified or deleted once issued', function () {
    payOrder(awaitingPaymentOrder());
    $invoice = Invoice::firstOrFail();

    expect(fn () => $invoice->update(['total_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $invoice->delete())->toThrow(LogicException::class)
        ->and(fn () => InvoiceLine::firstOrFail()->update(['name' => 'Falsifié']))->toThrow(LogicException::class);

    expect($invoice->refresh()->total_minor)->toBeGreaterThan(1);
});
