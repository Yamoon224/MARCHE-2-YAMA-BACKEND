<?php

declare(strict_types=1);

namespace App\Modules\Claims\Actions;

use App\Modules\Claims\Enums\ClaimResolution;
use App\Modules\Claims\Enums\ClaimStatus;
use App\Modules\Claims\Enums\CostBearer;
use App\Modules\Claims\Exceptions\ClaimNotAllowed;
use App\Modules\Claims\Models\Claim;
use App\Modules\Orders\Contracts\OrderClaimPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Payments\Contracts\RefundIssuer;
use App\Modules\Payments\Exceptions\RefundNotAllowed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DecideClaimAction
{
    public function __construct(
        private readonly OrderClaimPort $orders,
        private readonly RefundIssuer $refunds,
    ) {}

    /**
     * Décision motivée du personnel : rejet, ou acceptation avec remboursement (plafonné à la valeur des articles
     * réclamés) ou nouvel envoi, et imputation du coût. Une réclamation ne se décide qu'une fois.
     *
     * La décision est d'abord validée et enregistrée en une transaction (montant remboursable contrôlé, commande
     * clôturée) ; le remboursement part ensuite chez l'agrégateur, hors transaction et de façon idempotente : une
     * erreur réseau ne peut ni annuler la décision après coup, ni rembourser deux fois. Si l'agrégateur refuse,
     * le remboursement reste en échec et est repris automatiquement.
     *
     * @throws ClaimNotAllowed
     */
    public function execute(
        Claim $claim,
        int $staffId,
        bool $accept,
        string $note,
        ?ClaimResolution $resolution = null,
        ?CostBearer $costBearer = null,
        ?int $amountMinor = null,
    ): Claim {
        $refunding = $accept && $resolution === ClaimResolution::Refund;

        $decided = DB::transaction(function () use ($claim, $staffId, $accept, $note, $resolution, $costBearer, $amountMinor, $refunding) {
            $locked = Claim::with('items')->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ClaimStatus::Open) {
                throw ClaimNotAllowed::alreadyDecided();
            }

            if ($refunding) {
                $ceiling = $locked->claimableMinor();
                if ($amountMinor === null || $amountMinor < 1 || $amountMinor > $ceiling) {
                    throw ClaimNotAllowed::amountAboveCeiling($ceiling);
                }

                if ($this->refunds->refundable($locked->order_id) < $amountMinor) {
                    throw ClaimNotAllowed::refundImpossible('montant supérieur à ce qui a été encaissé et non encore remboursé.');
                }
            }

            try {
                $this->orders->markClaimResolved($locked->order_id, $staffId, $refunding);
            } catch (InvalidOrderTransition) {
                throw ClaimNotAllowed::alreadyDecided();
            }

            $locked->update([
                'status' => $accept ? ClaimStatus::Accepted : ClaimStatus::Rejected,
                'resolution' => $accept ? $resolution : null,
                'cost_bearer' => $accept ? $costBearer : null,
                'refund_amount_minor' => $refunding ? $amountMinor : 0,
                'refund_completed' => ! $refunding, // sans remboursement à faire, rien n'est en attente
                'decided_by' => $staffId,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            return $locked;
        });

        if ($refunding) {
            try {
                $outcome = $this->refunds->forClaim($decided->order_id, (int) $amountMinor, 'Réclamation acceptée', "claim:{$decided->id}");
                $decided->update(['refund_completed' => $outcome->completed]);
            } catch (RefundNotAllowed $e) {
                // Course rare (un autre remboursement entre-temps) : la décision reste, le geste est à traiter à la main.
                Log::warning("Remboursement de la réclamation {$decided->id} impossible : {$e->getMessage()}");
            }
        }

        return $decided->load('photos');
    }
}
