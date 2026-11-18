<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Http\Requests\PeriodRequest;
use App\Modules\Reporting\Services\ReportService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[OA\Schema(schema: 'Dashboard', required: ['period', 'currency', 'revenue_minor', 'orders_count', 'average_basket_minor', 'orders_by_status', 'sales_by_day', 'top_products', 'alerts'], properties: [
    new OA\Property(property: 'period', type: 'object', required: ['from', 'to'], properties: [
        new OA\Property(property: 'from', type: 'string', format: 'date'),
        new OA\Property(property: 'to', type: 'string', format: 'date'),
    ]),
    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
    new OA\Property(property: 'revenue_minor', type: 'integer', description: 'Chiffre d\'affaires TTC des commandes aboutissantes, en centimes'),
    new OA\Property(property: 'orders_count', type: 'integer', description: 'Commandes comptées comme ventes'),
    new OA\Property(property: 'average_basket_minor', type: 'integer'),
    new OA\Property(property: 'orders_by_status', type: 'array', items: new OA\Items(required: ['status', 'count'], properties: [
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'count', type: 'integer'),
    ])),
    new OA\Property(property: 'sales_by_day', type: 'array', description: 'Un point par jour de la période', items: new OA\Items(required: ['date', 'orders_count', 'revenue_minor'], properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'orders_count', type: 'integer'),
        new OA\Property(property: 'revenue_minor', type: 'integer'),
    ])),
    new OA\Property(property: 'top_products', type: 'array', items: new OA\Items(required: ['product_id', 'name', 'quantity', 'revenue_minor'], properties: [
        new OA\Property(property: 'product_id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'quantity', type: 'integer', description: 'Unité de base : pièces, grammes ou millilitres'),
        new OA\Property(property: 'revenue_minor', type: 'integer'),
    ])),
    new OA\Property(property: 'alerts', type: 'object', required: ['expiring_lots', 'expiring_lots_days', 'out_of_stock_products', 'open_claims', 'late_deliveries'], properties: [
        new OA\Property(property: 'expiring_lots', type: 'integer'),
        new OA\Property(property: 'expiring_lots_days', type: 'integer'),
        new OA\Property(property: 'out_of_stock_products', type: 'integer'),
        new OA\Property(property: 'open_claims', type: 'integer'),
        new OA\Property(property: 'late_deliveries', type: 'integer'),
    ]),
])]
class ReportController
{
    private const PERIOD_PARAMS = [
        ['from', 'Début de période (AAAA-MM-JJ), défaut : il y a 29 jours'],
        ['to', 'Fin de période incluse (AAAA-MM-JJ), défaut : aujourd\'hui'],
    ];

    #[OA\Get(
        path: '/api/v1/reports/dashboard',
        summary: 'Tableau de bord : CA, commandes, panier moyen, ventes par jour, meilleurs produits, alertes (permission reports.view)',
        security: [['sanctum' => []]],
        tags: ['Rapports'],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', description: 'AAAA-MM-JJ, défaut : il y a 29 jours', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', description: 'AAAA-MM-JJ inclus, défaut : aujourd\'hui', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Indicateurs', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Dashboard')])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Période invalide'),
        ],
    )]
    public function dashboard(PeriodRequest $request, ReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->dashboard($request->from(), $request->to())]);
    }

    #[OA\Get(
        path: '/api/v1/reports/sales',
        summary: 'Ventes regroupées par jour, produit ou catégorie, en JSON ou en CSV (permission reports.view)',
        security: [['sanctum' => []]],
        tags: ['Rapports'],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'group', in: 'query', schema: new OA\Schema(type: 'string', enum: ['day', 'product', 'category'], default: 'day')),
            new OA\Parameter(name: 'format', in: 'query', description: 'csv : téléchargement', schema: new OA\Schema(type: 'string', enum: ['json', 'csv'], default: 'json')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lignes de ventes (ou fichier CSV)', content: new OA\JsonContent(required: ['data'], properties: [
                new OA\Property(property: 'data', type: 'object', required: ['period', 'currency', 'group', 'rows', 'totals'], properties: [
                    new OA\Property(property: 'period', type: 'object', properties: [
                        new OA\Property(property: 'from', type: 'string', format: 'date'),
                        new OA\Property(property: 'to', type: 'string', format: 'date'),
                    ]),
                    new OA\Property(property: 'currency', type: 'string'),
                    new OA\Property(property: 'group', type: 'string'),
                    new OA\Property(property: 'rows', type: 'array', items: new OA\Items(required: ['key', 'label', 'revenue_minor'], properties: [
                        new OA\Property(property: 'key', type: 'string'),
                        new OA\Property(property: 'label', type: 'string'),
                        new OA\Property(property: 'orders_count', type: 'integer', nullable: true, description: 'Renseigné pour group=day'),
                        new OA\Property(property: 'quantity', type: 'integer', nullable: true, description: 'Renseigné pour group=product|category'),
                        new OA\Property(property: 'revenue_minor', type: 'integer'),
                    ])),
                    new OA\Property(property: 'totals', type: 'object', properties: [new OA\Property(property: 'revenue_minor', type: 'integer')]),
                ]),
            ])),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 422, description: 'Paramètres invalides'),
        ],
    )]
    public function sales(PeriodRequest $request, ReportService $reports): JsonResponse|StreamedResponse
    {
        $group = $request->string('group', 'day')->toString();
        $from = $request->from();
        $to = $request->to();
        $rows = $reports->sales($from, $to, $group);

        if ($request->string('format')->toString() === 'csv') {
            return $this->csv($rows, $group, $from->toDateString(), $to->toDateString());
        }

        return response()->json(['data' => [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => ReportService::CURRENCY,
            'group' => $group,
            'rows' => $rows,
            'totals' => ['revenue_minor' => array_sum(array_column($rows, 'revenue_minor'))],
        ]]);
    }

    /** @param  list<array{key: string, label: string, orders_count: int|null, quantity: int|null, revenue_minor: int}>  $rows */
    private function csv(array $rows, string $group, string $from, string $to): StreamedResponse
    {
        $labels = ['day' => 'Jour', 'product' => 'Produit', 'category' => 'Catégorie'];

        return response()->streamDownload(function () use ($rows, $group, $labels): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel ouvre l'UTF-8 correctement
            fputcsv($out, [$labels[$group], 'Commandes', 'Quantité', "Chiffre d'affaires (".ReportService::CURRENCY.')'], ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, [
                    // Neutralise l'injection de formules à l'ouverture dans un tableur.
                    preg_match('/^[=+\-@\t\r]/', $row['label']) === 1 ? "'".$row['label'] : $row['label'],
                    $row['orders_count'] ?? '',
                    $row['quantity'] ?? '',
                    number_format($row['revenue_minor'] / 100, 2, ',', ''),
                ], ';', '"', '');
            }
            fclose($out);
        }, "ventes-{$group}-{$from}-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
