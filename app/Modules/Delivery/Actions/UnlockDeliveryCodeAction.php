<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\Delivery;

final class UnlockDeliveryCodeAction
{
    /** Débloque la saisie du code après verrouillage (le responsable a vérifié l'identité du client par téléphone). */
    public function execute(Delivery $delivery): Delivery
    {
        $delivery->update(['code_attempts' => 0]);

        return $delivery;
    }
}
