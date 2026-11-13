<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Actions\BookSlotAction;
use App\Modules\Delivery\Actions\QuoteDeliveryAction;
use App\Modules\Orders\Contracts\DeliveryPort;
use App\Modules\Orders\DTO\DeliveryQuote;

final class EloquentDeliveryPort implements DeliveryPort
{
    public function __construct(
        private readonly QuoteDeliveryAction $quote,
        private readonly BookSlotAction $book,
    ) {}

    public function quote(int $zoneId, int $subtotalMinor): DeliveryQuote
    {
        return $this->quote->execute($zoneId, $subtotalMinor);
    }

    public function bookSlot(int $slotId, int $zoneId, int $orderId): void
    {
        $this->book->execute($slotId, $zoneId, $orderId);
    }
}
