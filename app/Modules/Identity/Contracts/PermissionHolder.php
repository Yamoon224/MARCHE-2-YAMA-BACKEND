<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/** API publique d'Identity pour les autres modules. */
interface PermissionHolder extends Authenticatable
{
    public function hasPermission(string $permission): bool;

    /** Vrai pour tout compte autre qu'un client (équipe du magasin). */
    public function isStaff(): bool;
}
