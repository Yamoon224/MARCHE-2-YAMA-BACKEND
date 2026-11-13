<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Actions;

use App\Modules\Fulfillment\Enums\PickListStatus;
use App\Modules\Fulfillment\Exceptions\PickingNotAllowed;
use App\Modules\Fulfillment\Models\PickList;
use Illuminate\Support\Facades\DB;

final class StartPickListAction
{
    /**
     * Prend en charge une préparation. Deux préparateurs qui cliquent en même temps : un seul l'obtient
     * (la ligne est verrouillée). Reprendre sa propre préparation est sans effet.
     *
     * @throws PickingNotAllowed
     */
    public function execute(PickList $list, int $pickerId): PickList
    {
        return DB::transaction(function () use ($list, $pickerId) {
            $locked = PickList::whereKey($list->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PickListStatus::Completed) {
                throw PickingNotAllowed::notInProgress();
            }

            if ($locked->picker_id !== null && $locked->picker_id !== $pickerId) {
                throw PickingNotAllowed::alreadyTaken();
            }

            if ($locked->status === PickListStatus::Open) {
                $locked->update(['status' => PickListStatus::InProgress, 'picker_id' => $pickerId, 'started_at' => now()]);
            }

            return $locked;
        });
    }
}
