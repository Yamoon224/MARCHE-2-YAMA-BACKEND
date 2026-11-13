<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Contracts\ContactDirectory;
use App\Modules\Notifications\Domain\Templates;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Services\DeliveryDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;

final class SendNotificationAction
{
    public function __construct(
        private readonly ContactDirectory $contacts,
        private readonly DeliveryDispatcher $dispatcher,
    ) {}

    /**
     * Notifie un client : toujours dans son espace, puis par SMS et e-mail s'il a laissé ces coordonnées.
     * Idempotent par `$dedupKey` (rejouer l'événement ne renvoie rien). Un envoi en échec est repris plus tard.
     *
     * @param  array{order_id?: int, amount_minor?: int}  $params
     */
    public function execute(int $userId, string $type, array $params, string $dedupKey): ?UserNotification
    {
        $contact = $this->contacts->contactOf($userId);
        if ($contact === null) {
            return null;
        }

        $text = Templates::render($type, $params);

        try {
            $notification = UserNotification::create([
                'user_id' => $userId, 'type' => $type, 'title' => $text['title'], 'body' => $text['body'], 'dedup_key' => $dedupKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $recipients = ['sms' => $contact['phone'], 'email' => $contact['email']];

        foreach ((array) config('notifications.media') as $medium) {
            $recipient = $recipients[$medium] ?? null;

            if (! is_string($recipient) || $recipient === '') {
                continue;
            }

            $delivery = NotificationDelivery::create([
                'user_notification_id' => $notification->id, 'medium' => $medium, 'recipient' => $recipient, 'status' => 'pending',
            ]);

            $this->dispatcher->attempt($delivery);
        }

        return $notification;
    }
}
