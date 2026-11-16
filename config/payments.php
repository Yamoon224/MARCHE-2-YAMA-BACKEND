<?php

use App\Modules\Payments\Gateways\FakeGateway;

return [
    // Moyens de paiement proposés en ligne (liste séparée par des virgules). Carte Visa / Mastercard uniquement par défaut ;
    // mobile_money et cash_on_delivery restent disponibles dans le code mais ne sont pas ouverts.
    'methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMENTS_METHODS', 'card'))))),

    // Agrégateur utilisé pour les nouveaux paiements.
    'default' => env('PAYMENTS_GATEWAY', 'fake'),

    'gateways' => [
        'fake' => [
            'driver' => FakeGateway::class,
            'secret' => env('PAYMENTS_FAKE_SECRET', 'local-dev-secret-change-me'),
        ],
    ],
];
