<?php

declare(strict_types=1);

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Events\PosSessionClosed;
use App\Modules\Pos\Exceptions\PosNotAllowed;
use App\Modules\Pos\Models\PosSale;
use App\Modules\Pos\Models\PosSession;
use Illuminate\Support\Facades\DB;

final class CloseSessionAction
{
    /**
     * Ferme la caisse après comptage du tiroir. Attendu = fonds de caisse + ventes en espèces ; un écart est accepté
     * mais exige une explication et est comptabilisé comme manquant ou excédent.
     *
     * @throws PosNotAllowed
     */
    public function execute(int $sessionId, int $cashierId, int $countedMinor, ?string $note): PosSession
    {
        $note = $note === null ? null : trim($note);

        $session = DB::transaction(function () use ($sessionId, $cashierId, $countedMinor, $note) {
            $session = PosSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->cashier_id !== $cashierId) {
                throw PosNotAllowed::notYours();
            }
            if (! $session->isOpen()) {
                throw PosNotAllowed::alreadyClosed();
            }

            $cashSales = (int) PosSale::where('pos_session_id', $session->id)->where('tender', 'cash')->sum('total_minor');
            $expected = $session->opening_float_minor + $cashSales;
            $variance = $countedMinor - $expected;

            if ($variance !== 0 && ($note === null || mb_strlen($note) < 3)) {
                throw PosNotAllowed::varianceNeedsNote($expected);
            }

            $session->update([
                'expected_cash_minor' => $expected, 'counted_minor' => $countedMinor, 'variance_minor' => $variance,
                'note' => $note === '' ? null : $note, 'open_key' => null, 'closed_at' => now(),
            ]);

            return $session;
        });

        PosSessionClosed::dispatch($session->id, $session->cashier_id, (int) $session->expected_cash_minor, (int) $session->counted_minor);

        return $session;
    }
}
