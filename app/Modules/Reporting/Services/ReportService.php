<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Contracts\ClaimAlertReader;
use App\Modules\Reporting\Contracts\DeliveryAlertReader;
use App\Modules\Reporting\Contracts\ProductCategoryReader;
use App\Modules\Reporting\Contracts\SalesReader;
use App\Modules\Reporting\Contracts\StockAlertReader;
use Carbon\CarbonImmutable;

/** Assemble les indicateurs de pilotage ; ne lit les autres modules que par leurs contrats. */
final class ReportService
{
    public const CURRENCY = 'EUR';

    public const EXPIRY_ALERT_DAYS = 7;

    public const TOP_PRODUCTS = 10;

    public function __construct(
        private readonly SalesReader $sales,
        private readonly ProductCategoryReader $categories,
        private readonly StockAlertReader $stock,
        private readonly ClaimAlertReader $claims,
        private readonly DeliveryAlertReader $deliveries,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->sales->summary($from, $to);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => self::CURRENCY,
            'revenue_minor' => $summary['revenue_minor'],
            'orders_count' => $summary['orders_count'],
            'average_basket_minor' => $summary['orders_count'] > 0 ? intdiv($summary['revenue_minor'], $summary['orders_count']) : 0,
            'orders_by_status' => $this->sales->ordersByStatus($from, $to),
            'sales_by_day' => $this->salesByDay($from, $to),
            'top_products' => array_slice($this->sales->byProduct($from, $to), 0, self::TOP_PRODUCTS),
            'alerts' => [
                'expiring_lots' => $this->stock->expiringLots(self::EXPIRY_ALERT_DAYS),
                'expiring_lots_days' => self::EXPIRY_ALERT_DAYS,
                'out_of_stock_products' => $this->stock->outOfStockProducts(),
                'open_claims' => $this->claims->openClaims(),
                'late_deliveries' => $this->deliveries->lateDeliveries(),
            ],
        ];
    }

    /**
     * Ventes regroupées ; tableau de lignes {key, label, orders_count|null, quantity|null, revenue_minor}.
     *
     * @return list<array{key: string, label: string, orders_count: int|null, quantity: int|null, revenue_minor: int}>
     */
    public function sales(CarbonImmutable $from, CarbonImmutable $to, string $group): array
    {
        return match ($group) {
            'product' => array_map(fn (array $p) => [
                'key' => (string) $p['product_id'], 'label' => $p['name'], 'orders_count' => null,
                'quantity' => $p['quantity'], 'revenue_minor' => $p['revenue_minor'],
            ], $this->sales->byProduct($from, $to)),
            'category' => $this->byCategory($from, $to),
            default => array_map(fn (array $d) => [
                'key' => $d['date'], 'label' => $d['date'], 'orders_count' => $d['orders_count'],
                'quantity' => null, 'revenue_minor' => $d['revenue_minor'],
            ], $this->salesByDay($from, $to)),
        };
    }

    /**
     * Série continue : un point par jour de la période, même sans vente.
     *
     * @return list<array{date: string, orders_count: int, revenue_minor: int}>
     */
    private function salesByDay(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDate = [];
        foreach ($this->sales->byDay($from, $to) as $row) {
            $byDate[$row['date']] = $row;
        }

        $series = [];
        for ($day = $from->startOfDay(); $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $series[] = $byDate[$date] ?? ['date' => $date, 'orders_count' => 0, 'revenue_minor' => 0];
        }

        return $series;
    }

    /** @return list<array{key: string, label: string, orders_count: int|null, quantity: int|null, revenue_minor: int}> */
    private function byCategory(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $products = $this->sales->byProduct($from, $to);
        $categories = $this->categories->categoriesOf(array_map(fn (array $p) => $p['product_id'], $products));

        $totals = [];
        foreach ($products as $product) {
            $category = $categories[$product['product_id']] ?? ['id' => 0, 'name' => 'Sans catégorie'];
            $key = (string) $category['id'];
            $totals[$key] ??= ['key' => $key, 'label' => $category['name'], 'orders_count' => null, 'quantity' => 0, 'revenue_minor' => 0];
            $totals[$key]['quantity'] += $product['quantity'];
            $totals[$key]['revenue_minor'] += $product['revenue_minor'];
        }

        $rows = array_values($totals);
        usort($rows, fn (array $a, array $b) => $b['revenue_minor'] <=> $a['revenue_minor']);

        return $rows;
    }
}
