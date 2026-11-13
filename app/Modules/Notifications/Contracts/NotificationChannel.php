<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Exceptions\ChannelFailure;

/** Port d'envoi vers un fournisseur externe (SMS, e-mail). Le vrai fournisseur se branche ici, rien d'autre ne change. */
interface NotificationChannel
{
    /** @throws ChannelFailure */
    public function send(string $recipient, string $title, string $body): void;
}
