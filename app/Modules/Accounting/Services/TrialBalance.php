<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

final class TrialBalance
{
    /**
     * @return array{accounts: list<array{code: string, name: string, debit_minor: int, credit_minor: int, balance_minor: int}>, total_debit_minor: int, total_credit_minor: int, balanced: bool}
     */
    public function compute(): array
    {
        $rows = DB::table('accounts')
            ->leftJoin('journal_lines', 'journal_lines.account_code', '=', 'accounts.code')
            ->groupBy('accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get([
                'accounts.code', 'accounts.name',
                DB::raw('COALESCE(SUM(journal_lines.debit_minor), 0) as debit'),
                DB::raw('COALESCE(SUM(journal_lines.credit_minor), 0) as credit'),
            ]);

        $accounts = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($rows as $row) {
            $debit = (int) $row->debit;
            $credit = (int) $row->credit;
            $totalDebit += $debit;
            $totalCredit += $credit;
            $accounts[] = [
                'code' => (string) $row->code, 'name' => (string) $row->name,
                'debit_minor' => $debit, 'credit_minor' => $credit, 'balance_minor' => $debit - $credit,
            ];
        }

        return [
            'accounts' => $accounts,
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'balanced' => $totalDebit === $totalCredit,
        ];
    }
}
