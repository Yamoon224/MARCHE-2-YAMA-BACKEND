<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Contracts\NotificationChannel;
use App\Modules\Notifications\Exceptions\ChannelFailure;
use Illuminate\Support\Facades\Log;

/** Fournisseur de test : n'envoie rien, journalise sans le contenu du destinataire en clair. Interdit en production. */
final class LogChannel implements NotificationChannel
{
    public function __construct(private readonly string $medium) {}

    public function send(string $recipient, string $title, string $body): void
    {
        if (app()->isProduction()) {
            throw ChannelFailure::unavailable($this->medium);
        }

        if (config('notifications.fail_sends') === true) {
            throw ChannelFailure::providerDown($this->medium);
        }

        Log::info("[notification:{$this->medium}] {$title}", ['recipient_hash' => substr(hash('sha256', $recipient), 0, 12)]);
    }
}
