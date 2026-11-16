<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les parcours historiques (mobile money, paiement à la livraison) restent testés ; la carte seule
        // est la valeur par défaut en production et fait l'objet de tests dédiés.
        config(['payments.methods' => ['mobile_money', 'card', 'cash_on_delivery']]);
    }
}
