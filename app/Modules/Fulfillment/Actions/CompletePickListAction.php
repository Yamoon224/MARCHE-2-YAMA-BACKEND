<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Actions;

use App\Modules\Fulfillment\Enums\PickLineStatus;
use App\Modules\Fulfillment\Enums\PickListStatus;
use App\Modules\Fulfillment\Exceptions\PickingNotAllowed;
use App\Modules\Fulfillment\Models\PickList;
use App\Modules\Orders\Contracts\OrderPickingPort;
use App\Modules\Orders\DTO\PickResult;
use App\Modules\Orders\Exceptions\InsufficientStock;
use Illuminate\Support\Facades\DB;

final class CompletePickListAction
{
    public function __construct(private readonly OrderPickingPort $orders) {}

    /**
     * Termine la préparation : tous les articles doivent être traités. Le résultat est transmis à la
     * commande (totaux réels, stock ajusté, commande « prête »). Si un remplaçant n'est plus disponible,
     * rien n'est modifié.
     *
     * @return int écart (réel - payé) : négatif = à rembourser, positif = à encaisser
     *
     * @throws PickingNotAllowed
     * @throws InsufficientStock
     */
    public function execute(PickList $list, ?int $userId): int
    {
        return DB::transaction(function () use ($list, $userId) {
            $locked = PickList::with('lines')->whereKey($list->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PickListStatus::InProgress) {
                throw PickingNotAllowed::notInProgress();
            }

            $pending = $locked->lines->where('status', PickLineStatus::Pending)->count();
            if ($pending > 0) {
                throw PickingNotAllowed::linesPending($pending);
            }

            $results = $locked->lines->map(function ($line) {
                if ($line->status === PickLineStatus::Substituted) {
                    return new PickResult(
                        $line->order_line_id, (int) $line->final_product_id, (string) $line->final_name,
                        (int) $line->picked_quantity, (int) $line->final_unit_price_minor, true,
                    );
                }

                return new PickResult(
                    $line->order_line_id, $line->product_id, $line->name,
                    (int) $line->picked_quantity, $line->unit_price_minor, false,
                );
            })->all();

            $delta = $this->orders->applyResult($locked->order_id, $results, $userId);

            $locked->update(['status' => PickListStatus::Completed, 'completed_at' => now()]);

            return $delta;
        });
    }
}
