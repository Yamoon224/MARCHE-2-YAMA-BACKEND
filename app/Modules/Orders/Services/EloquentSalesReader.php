<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Reporting\Contracts\SalesReader;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentSalesReader implements SalesReader
{
    private const AMOUNT = 'COALESCE(orders.final_total_minor, orders.total_minor)';

    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->sales($from, $to)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM('.self::AMOUNT.'), 0) as revenue_minor')
            ->first();

        return ['orders_count' => (int) ($row->orders_count ?? 0), 'revenue_minor' => (int) ($row->revenue_minor ?? 0)];
    }

    public function ordersByStatus(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('orders')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['status' => (string) $row->status, 'count' => (int) $row->count])
            ->all();
    }

    public function byDay(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->sales($from, $to)
            ->selectRaw('DATE(orders.created_at) as day, COUNT(*) as orders_count, SUM('.self::AMOUNT.') as revenue_minor')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->day,
                'orders_count' => (int) $row->orders_count,
                'revenue_minor' => (int) $row->revenue_minor,
            ])
            ->all();
    }

    public function byProduct(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->sales($from, $to)
            ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
            ->selectRaw('order_lines.product_id as product_id, MAX(order_lines.name) as name, '
                .'SUM(COALESCE(order_lines.final_quantity, order_lines.quantity)) as quantity, '
                .'SUM(COALESCE(order_lines.final_line_total_minor, order_lines.line_total_minor)) as revenue_minor')
            ->groupBy('order_lines.product_id')
            ->orderByDesc('revenue_minor')
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
                'revenue_minor' => (int) $row->revenue_minor,
            ])
            ->all();
    }

    private function sales(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('orders')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', array_map(fn (OrderStatus $s) => $s->value, SalesStatuses::counted()));
    }
}
