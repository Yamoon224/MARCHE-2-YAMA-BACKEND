<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\TrialBalance;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class TrialBalanceController
{
    #[OA\Get(
        path: '/api/v1/accounting/trial-balance',
        summary: 'Balance générale (débits, crédits et solde par compte)',
        security: [['sanctum' => []]],
        tags: ['Comptabilité'],
        responses: [
            new OA\Response(response: 200, description: 'Balance', content: new OA\JsonContent(
                required: ['accounts', 'total_debit_minor', 'total_credit_minor', 'balanced'],
                properties: [
                    new OA\Property(property: 'accounts', type: 'array', items: new OA\Items(
                        required: ['code', 'name', 'debit_minor', 'credit_minor', 'balance_minor'],
                        properties: [
                            new OA\Property(property: 'code', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'debit_minor', type: 'integer'),
                            new OA\Property(property: 'credit_minor', type: 'integer'),
                            new OA\Property(property: 'balance_minor', type: 'integer'),
                        ],
                    )),
                    new OA\Property(property: 'total_debit_minor', type: 'integer'),
                    new OA\Property(property: 'total_credit_minor', type: 'integer'),
                    new OA\Property(property: 'balanced', type: 'boolean'),
                ],
            )),
            new OA\Response(response: 403, description: 'Interdit'),
        ],
    )]
    public function __invoke(TrialBalance $trialBalance): JsonResponse
    {
        return response()->json($trialBalance->compute());
    }
}
