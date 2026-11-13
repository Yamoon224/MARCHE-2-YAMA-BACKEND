<?php

arch('Inventory only reaches Orders through its public contracts, events and enums')
    ->expect('App\Modules\Inventory')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Identity', 'App\Modules\Payments', 'App\Modules\Invoicing', 'App\Modules\Accounting',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain',
    ]);

arch('Catalog only reaches Orders through its public contracts')
    ->expect('App\Modules\Catalog')
    ->not->toUse([
        'App\Modules\Inventory', 'App\Modules\Payments', 'App\Modules\Invoicing', 'App\Modules\Accounting',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain',
    ]);

arch('Identity is independent from business modules')
    ->expect('App\Modules\Identity')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Orders', 'App\Modules\Payments',
        'App\Modules\Invoicing', 'App\Modules\Accounting',
    ]);

arch('Orders never touches other modules internals')
    ->expect('App\Modules\Orders')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Payments', 'App\Modules\Invoicing', 'App\Modules\Accounting',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions',
    ]);

arch('Payments reaches Orders only through ports and DTOs')
    ->expect('App\Modules\Payments')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Invoicing', 'App\Modules\Accounting',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain', 'App\Modules\Orders\Enums',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions',
    ]);

arch('Invoicing reaches Orders only through ports and public events')
    ->expect('App\Modules\Invoicing')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Accounting',
        'App\Modules\Payments\Models', 'App\Modules\Payments\Actions', 'App\Modules\Payments\Gateways',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions',
    ]);

arch('Accounting only listens to public events of Payments and Invoicing')
    ->expect('App\Modules\Accounting')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Orders', 'App\Modules\Identity',
        'App\Modules\Payments\Models', 'App\Modules\Payments\Actions', 'App\Modules\Payments\Gateways',
        'App\Modules\Invoicing\Models', 'App\Modules\Invoicing\Actions',
    ]);

arch('Fulfillment reaches Orders only through ports, DTOs and exceptions')
    ->expect('App\Modules\Fulfillment')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Payments', 'App\Modules\Invoicing',
        'App\Modules\Accounting', 'App\Modules\Delivery',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain', 'App\Modules\Orders\Services',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions',
    ]);

arch('no other module depends on Fulfillment')
    ->expect(['App\Modules\Orders', 'App\Modules\Inventory', 'App\Modules\Catalog', 'App\Modules\Payments', 'App\Modules\Invoicing', 'App\Modules\Accounting', 'App\Modules\Delivery', 'App\Modules\Identity'])
    ->not->toUse('App\Modules\Fulfillment');

arch('Delivery reaches other modules only through ports, DTOs and public events')
    ->expect('App\Modules\Delivery')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Invoicing', 'App\Modules\Accounting', 'App\Modules\Fulfillment',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain', 'App\Modules\Orders\Services',
        'App\Modules\Payments\Models', 'App\Modules\Payments\Actions', 'App\Modules\Payments\Gateways', 'App\Modules\Payments\Services',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions', 'App\Modules\Identity\Services',
    ]);

arch('Claims reaches other modules only through ports, DTOs, exceptions and contracts')
    ->expect('App\Modules\Claims')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Invoicing', 'App\Modules\Accounting',
        'App\Modules\Fulfillment', 'App\Modules\Delivery',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain', 'App\Modules\Orders\Services',
        'App\Modules\Payments\Models', 'App\Modules\Payments\Actions', 'App\Modules\Payments\Gateways', 'App\Modules\Payments\Services',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions', 'App\Modules\Identity\Services',
    ]);

arch('ledger and invoice models are immutable')
    ->expect([
        'App\Modules\Accounting\Models\JournalEntry', 'App\Modules\Accounting\Models\JournalLine',
        'App\Modules\Invoicing\Models\Invoice', 'App\Modules\Invoicing\Models\InvoiceLine',
    ])
    ->toUseTrait('App\Shared\Concerns\Immutable');

arch('payment gateways are interchangeable implementations of the port')
    ->expect('App\Modules\Payments\Gateways\FakeGateway')
    ->toImplement('App\Modules\Payments\Contracts\PaymentGateway');

arch('modules use strict types')
    ->expect('App\Modules')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump'])
    ->not->toBeUsed();

arch('Notifications only listens to public events and contracts of other modules')
    ->expect('App\Modules\Notifications')
    ->not->toUse([
        'App\Modules\Catalog', 'App\Modules\Inventory', 'App\Modules\Invoicing', 'App\Modules\Accounting',
        'App\Modules\Delivery', 'App\Modules\Fulfillment', 'App\Modules\Claims',
        'App\Modules\Orders\Models', 'App\Modules\Orders\Actions', 'App\Modules\Orders\Domain',
        'App\Modules\Payments\Models', 'App\Modules\Payments\Actions', 'App\Modules\Payments\Gateways',
        'App\Modules\Identity\Models', 'App\Modules\Identity\Enums', 'App\Modules\Identity\Actions',
    ]);

arch('Notification channels implement the channel port')
    ->expect('App\Modules\Notifications\Channels')
    ->toImplement('App\Modules\Notifications\Contracts\NotificationChannel');
