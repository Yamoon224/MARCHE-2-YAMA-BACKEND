<?php

declare(strict_types=1);

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Exceptions\PosNotAllowed;
use App\Modules\Pos\Models\PosSession;
use Illuminate\Database\UniqueConstraintViolationException;

final class OpenSessionAction
{
    /** Ouvre la caisse d'un caissier avec son fonds de caisse. Une seule caisse ouverte par caissier (garantie par la base). */
    public function execute(int $cashierId, int $openingFloatMinor): PosSession
    {
        try {
            return PosSession::create(['cashier_id' => $cashierId, 'opening_float_minor' => $openingFloatMinor, 'open_key' => 1]);
        } catch (UniqueConstraintViolationException) {
            throw PosNotAllowed::alreadyOpen();
        }
    }
}
