<?php

use App\Modules\Payments\Gateways\FakeGateway;

return [
    // Agrégateur utilisé pour les nouveaux paiements.
    'default' => env('PAYMENTS_GATEWAY', 'fake'),

    'gateways' => [
        'fake' => [
            'driver' => FakeGateway::class,
            'secret' => env('PAYMENTS_FAKE_SECRET', 'local-dev-secret-change-me'),
        ],
    ],
];
