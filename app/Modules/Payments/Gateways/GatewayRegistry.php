<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Exceptions\InvalidWebhook;

final class GatewayRegistry
{
    public function default(): PaymentGateway
    {
        return $this->get((string) config('payments.default'));
    }

    public function get(string $name): PaymentGateway
    {
        $config = config("payments.gateways.{$name}");

        // Le simulateur a un secret connu : il ne doit jamais être joignable en production.
        if (! is_array($config) || ($name === 'fake' && app()->isProduction())) {
            throw InvalidWebhook::unknownProvider($name);
        }

        /** @var class-string<PaymentGateway> $driver */
        $driver = $config['driver'];

        // Un driver à configuration riche (clé d'API, adresse de retour…) se construit lui-même.
        return method_exists($driver, 'fromConfig') ? $driver::fromConfig($config) : new $driver((string) $config['secret']);
    }
}
