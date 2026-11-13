<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Enums\DeliveryStatus;
use App\Modules\Delivery\Events\CashHandedOver;
use App\Modules\Delivery\Exceptions\DeliveryNotAllowed;
use App\Modules\Delivery\Models\CashHandover;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Contracts\CourierDirectory;
use Illuminate\Support\Facades\DB;

final class RecordCashHandoverAction
{
    public function __construct(private readonly CourierDirectory $couriers) {}

    /**
     * Le livreur remet ses espèces : tout ce qu'il a encaissé et pas encore remis est soldé d'un bloc. Un écart
     * (compté ≠ attendu) est accepté mais exige une explication et est comptabilisé comme perte ou excédent.
     *
     * @throws DeliveryNotAllowed
     */
    public function execute(int $courierId, int $receivedBy, int $countedMinor, ?string $note): CashHandover
    {
        if (! $this->couriers->isCourier($courierId)) {
            throw DeliveryNotAllowed::notACourier();
        }

        $note = $note === null ? null : trim($note);

        $handover = DB::transaction(function () use ($courierId, $receivedBy, $countedMinor, $note) {
            $deliveries = Delivery::where('courier_id', $courierId)
                ->where('status', DeliveryStatus::Delivered)
                ->whereNotNull('cash_collected_minor')
                ->whereNull('cash_handover_id')
                ->lockForUpdate()
                ->get();

            if ($deliveries->isEmpty()) {
                throw DeliveryNotAllowed::nothingToHandOver();
            }

            $expected = (int) $deliveries->sum('cash_collected_minor');
            $variance = $countedMinor - $expected;

            if ($variance !== 0 && ($note === null || mb_strlen($note) < 3)) {
                throw DeliveryNotAllowed::varianceNeedsNote($expected);
            }

            $handover = CashHandover::create([
                'courier_id' => $courierId, 'received_by' => $receivedBy, 'expected_minor' => $expected,
                'counted_minor' => $countedMinor, 'variance_minor' => $variance,
                'deliveries_count' => $deliveries->count(), 'note' => $note === '' ? null : $note,
            ]);

            Delivery::whereIn('id', $deliveries->modelKeys())->update(['cash_handover_id' => $handover->id]);

            return $handover;
        });

        CashHandedOver::dispatch($handover->id, $courierId, $handover->expected_minor, $handover->counted_minor);

        return $handover;
    }
}
