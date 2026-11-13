<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Inventory\Events\LotRecalled;
use App\Modules\Notifications\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\Templates;
use Throwable;

/** Alerte sanitaire : chaque client qui a reçu le lot est prévenu, par commande concernée. */
final class NotifyOnLotRecall
{
    public function __construct(private readonly SendNotificationAction $send) {}

    public function handle(LotRecalled $event): void
    {
        foreach ($event->deliveredTo as $row) {
            // Un échec pour un client ne doit jamais empêcher d'alerter les autres.
            try {
                $this->send->execute($row['customer_id'], Templates::LOT_RECALLED, [
                    'order_id' => $row['order_id'], 'lot_code' => $event->lotCode, 'reason' => $event->reason,
                ], "recall:{$event->lotId}:order:{$row['order_id']}");
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
