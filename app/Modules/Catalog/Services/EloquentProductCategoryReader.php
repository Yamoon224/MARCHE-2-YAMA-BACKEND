<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Reporting\Contracts\ProductCategoryReader;
use Illuminate\Support\Facades\DB;

final class EloquentProductCategoryReader implements ProductCategoryReader
{
    public function categoriesOf(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $map = [];
        $rows = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('products.id', $productIds)
            ->get(['products.id as product_id', 'categories.id as category_id', 'categories.name as category_name']);

        foreach ($rows as $row) {
            $map[(int) $row->product_id] = ['id' => (int) $row->category_id, 'name' => (string) $row->category_name];
        }

        return $map;
    }
}
