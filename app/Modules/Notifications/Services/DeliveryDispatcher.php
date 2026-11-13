<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Channels\LogChannel;
use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Exceptions\ChannelFailure;
use App\Modules\Notifications\Models\NotificationDelivery;

/** Tente un envoi et en consigne le résultat. Ne lève jamais : un fournisseur en panne ne doit rien bloquer. */
final class DeliveryDispatcher
{
    public function attempt(NotificationDelivery $delivery): bool
    {
        $notification = $delivery->notification;

        try {
            $this->channel($delivery->medium)->send($delivery->recipient, $notification->title, $notification->body);
        } catch (ChannelFailure $e) {
            $delivery->update(['status' => 'failed', 'attempts' => $delivery->attempts + 1, 'last_error' => mb_substr($e->getMessage(), 0, 250)]);

            return false;
        }

        $delivery->update(['status' => 'sent', 'attempts' => $delivery->attempts + 1, 'last_error' => null, 'sent_at' => now()]);

        return true;
    }

    private function channel(string $medium): NotificationChannel
    {
        return match (config('notifications.driver')) {
            'log' => new LogChannel($medium),
            default => throw ChannelFailure::unavailable($medium),
        };
    }
}
