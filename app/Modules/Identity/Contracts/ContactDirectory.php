<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/** API publique d'Identity : comment joindre un utilisateur (jamais ses autres données). */
interface ContactDirectory
{
    /** @return array{name: string, email: string|null, phone: string|null}|null null : compte inconnu */
    public function contactOf(int $userId): ?array;
}
