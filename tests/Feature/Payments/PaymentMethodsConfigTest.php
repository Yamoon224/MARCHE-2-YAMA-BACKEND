<?php

it('opens card and cash on delivery by default', function () {
    $config = require base_path('config/payments.php');

    expect($config['methods'])->toBe(['card', 'cash_on_delivery']);
});

it('accepts a card payment when only card is open', function () {
    config(['payments.methods' => ['card']]);
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'card')->assertCreated();
});

it('refuses mobile money whatever the default, and cash on delivery when only card is open', function () {
    config(['payments.methods' => ['card']]);
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'mobile_money')->assertUnprocessable()->assertJsonValidationErrors('method');
    startPayment($customer, $orderId, 'cash_on_delivery')->assertUnprocessable()->assertJsonValidationErrors('method');

    config(['payments.methods' => ['card', 'cash_on_delivery']]);
    startPayment($customer, $orderId, 'cash_on_delivery')->assertCreated()->assertJsonPath('data.status', 'pending_on_delivery');
});
