<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Services\DeliveryDispatcher;

final class RetryFailedDeliveriesAction
{
    public function __construct(private readonly DeliveryDispatcher $dispatcher) {}

    /** Reprend les envois en échec (ou restés en attente) tant qu'ils n'ont pas atteint le maximum de tentatives. */
    public function execute(): int
    {
        $max = (int) config('notifications.max_attempts');
        $recovered = 0;

        NotificationDelivery::whereIn('status', ['failed', 'pending'])->where('attempts', '<', $max)->orderBy('id')->limit(200)->get()
            ->each(function (NotificationDelivery $delivery) use (&$recovered) {
                if ($this->dispatcher->attempt($delivery)) {
                    $recovered++;
                }
            });

        return $recovered;
    }
}
