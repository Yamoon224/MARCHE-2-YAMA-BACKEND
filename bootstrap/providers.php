<?php

use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Delivery\Providers\DeliveryServiceProvider;
use App\Modules\Fulfillment\Providers\FulfillmentServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Invoicing\Providers\InvoicingServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
    InvoicingServiceProvider::class,
    AccountingServiceProvider::class,
    DeliveryServiceProvider::class,
    FulfillmentServiceProvider::class,
];
