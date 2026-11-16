<?php

it('opens only card payments by default', function () {
    $config = require base_path('config/payments.php');

    expect($config['methods'])->toBe(['card']);
});

it('accepts a card payment when only card is open', function () {
    config(['payments.methods' => ['card']]);
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'card')->assertCreated();
});

it('refuses mobile money and cash on delivery when only card is open', function () {
    config(['payments.methods' => ['card']]);
    ['customer' => $customer, 'orderId' => $orderId] = awaitingPaymentOrder();

    startPayment($customer, $orderId, 'mobile_money')->assertUnprocessable()->assertJsonValidationErrors('method');
    startPayment($customer, $orderId, 'cash_on_delivery')->assertUnprocessable()->assertJsonValidationErrors('method');
});
