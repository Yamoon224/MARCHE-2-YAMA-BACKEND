<?php

use App\Modules\Payments\Gateways\FakeGateway;
use App\Modules\Payments\Gateways\StripeGateway;

return [
    // Moyens de paiement proposés en ligne (liste séparée par des virgules). Carte Visa / Mastercard et espèces à la réception (livraison ou retrait) par défaut ;
    // mobile_money reste disponible dans le code mais n'est pas ouvert.
    'methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMENTS_METHODS', 'card,cash_on_delivery'))))),

    // Agrégateur utilisé pour les nouveaux paiements.
    'default' => env('PAYMENTS_GATEWAY', env('APP_ENV') === 'production' ? 'stripe' : 'fake'),

    'gateways' => [
        // Stripe Checkout. Renseigner STRIPE_SECRET_KEY (sk_…) et STRIPE_WEBHOOK_SECRET (whsec_…) pour activer le paiement par carte.
        // Webhook à déclarer dans Stripe : {APP_URL}/api/v1/webhooks/payments/stripe
        // (checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired).
        'stripe' => [
            'driver' => StripeGateway::class,
            'api_key' => env('STRIPE_SECRET_KEY'),
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            // Adresse publique de la vitrine : Stripe y renvoie le client après le paiement (/commandes/{id}).
            'return_url' => env('STRIPE_RETURN_URL', env('APP_URL')),
            'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
        ],

        'fake' => [
            'driver' => FakeGateway::class,
            'secret' => env('PAYMENTS_FAKE_SECRET', 'local-dev-secret-change-me'),
        ],
    ],
];
