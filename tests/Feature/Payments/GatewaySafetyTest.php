<?php

use App\Modules\Payments\Exceptions\InvalidWebhook;
use App\Modules\Payments\Gateways\FakeGateway;
use App\Modules\Payments\Gateways\GatewayRegistry;

it('exposes the simulated gateway outside production', function () {
    expect(app(GatewayRegistry::class)->get('fake')->name())->toBe('fake');
});

it('never exposes the simulated gateway in production, webhooks included', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(GatewayRegistry::class)->get('fake'))->toThrow(InvalidWebhook::class);

    $body = json_encode(webhookFor('fake_x', 'succeeded', 100, 'evt-prod'));
    $forged = (new FakeGateway('local-dev-secret-change-me'))->sign($body);

    sendWebhook(webhookFor('fake_x', 'succeeded', 100, 'evt-prod'), $forged)->assertNotFound();
});
