<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Contracts;

/** Alertes de réclamations, fournies par le module Réclamations. */
interface ClaimAlertReader
{
    public function openClaims(): int;
}
