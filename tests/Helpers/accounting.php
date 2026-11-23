<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Invoicing\Models\CreditNote;
use App\Modules\Payments\Models\Refund;
use Illuminate\Support\Facades\DB;

/** Solde (débit - crédit) d'un compte du grand livre. */
function ledger(string $account): int
{
    return (int) DB::table('journal_lines')->where('account_code', $account)->sum('debit_minor')
        - (int) DB::table('journal_lines')->where('account_code', $account)->sum('credit_minor');
}

/** Le grand livre est équilibré : total des débits = total des crédits. */
function ledgerIsBalanced(): bool
{
    return (int) DB::table('journal_lines')->sum('debit_minor') === (int) DB::table('journal_lines')->sum('credit_minor');
}

/** Annule une commande par l'API, avec motif, en tant qu'administrateur. */
function cancelOrder(int $orderId, string $reason = 'Annulation test')
{
    return test()->actingAs(User::factory()->role(Role::Admin)->create(), 'sanctum')
        ->postJson('/api/v1/orders/'.orderUuid($orderId).'/transitions', ['to' => 'cancelled', 'reason' => $reason]);
}

function refundsOf(int $orderId)
{
    return Refund::where('order_id', $orderId)->orderBy('id')->get();
}

function creditNotesOf(int $orderId)
{
    return CreditNote::where('order_id', $orderId)->orderBy('id')->get();
}
