<?php

declare(strict_types=1);

namespace App\Modules\Claims\Services;

use Illuminate\Support\Facades\DB;

/** Statistiques de qualité : quels produits posent problème, qui supporte le coût. */
final class QualityStats
{
    /**
     * @return array{
     *     claims_total: int, accepted: int, rejected: int, open: int, refunded_minor: int,
     *     by_product: list<array{product_id: int, name: string, claims: int, units: int}>,
     *     by_issue: list<array{issue: string, items: int}>,
     *     by_cost_bearer: list<array{cost_bearer: string, claims: int, refunded_minor: int}>
     * }
     */
    public function compute(): array
    {
        $status = DB::table('claims')->selectRaw('status, COUNT(*) as c, COALESCE(SUM(refund_amount_minor),0) as refunded')->groupBy('status')->get()->keyBy('status');

        return [
            'claims_total' => (int) $status->sum('c'),
            'accepted' => (int) ($status['accepted']->c ?? 0),
            'rejected' => (int) ($status['rejected']->c ?? 0),
            'open' => (int) ($status['open']->c ?? 0),
            'refunded_minor' => (int) $status->sum('refunded'),
            'by_product' => DB::table('claim_items')
                ->selectRaw('product_id, MAX(name) as name, COUNT(DISTINCT claim_id) as claims, SUM(quantity) as units')
                ->groupBy('product_id')->orderByDesc('claims')->orderBy('product_id')->limit(20)->get()
                ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'name' => (string) $r->name, 'claims' => (int) $r->claims, 'units' => (int) $r->units])->all(),
            'by_issue' => DB::table('claim_items')->selectRaw('issue, COUNT(*) as items')->groupBy('issue')->orderByDesc('items')->get()
                ->map(fn ($r) => ['issue' => (string) $r->issue, 'items' => (int) $r->items])->all(),
            'by_cost_bearer' => DB::table('claims')->whereNotNull('cost_bearer')
                ->selectRaw('cost_bearer, COUNT(*) as claims, COALESCE(SUM(refund_amount_minor),0) as refunded')->groupBy('cost_bearer')->orderByDesc('refunded')->get()
                ->map(fn ($r) => ['cost_bearer' => (string) $r->cost_bearer, 'claims' => (int) $r->claims, 'refunded_minor' => (int) $r->refunded])->all(),
        ];
    }
}
