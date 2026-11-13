<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTO\InvoiceableOrder;

/** Port : Invoicing lit les commandes sans connaître leurs modèles. */
interface OrderInvoiceReader
{
    public function find(int $orderId): ?InvoiceableOrder;
}
