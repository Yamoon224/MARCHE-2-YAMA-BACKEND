<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/** API publique d'Identity : le module Delivery vérifie qu'un compte est bien un livreur sans connaître les modèles. */
interface CourierDirectory
{
    public function isCourier(int $userId): bool;

    /** @return list<array{id: int, name: string}> les livreurs, par ordre alphabétique */
    public function all(): array;

    /** Nom affichable du livreur (suivi client), ou null s'il n'existe pas. */
    public function nameOf(int $userId): ?string;
}
