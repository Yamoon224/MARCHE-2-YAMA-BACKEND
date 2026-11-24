<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Vide le catalogue, et avec lui les commandes qui contiennent un de ses produits.
 *
 * Les autres modules n'ont volontairement pas de clé étrangère vers Orders (frontière de module) : la suppression
 * d'une commande doit donc être propagée à la main, table par table. Le grand livre comptable est en écriture seule
 * (déclencheurs MySQL) : il n'est jamais touché, ses écritures gardent simplement leur référence d'origine.
 */
final class CatalogPurger
{
    /** @return array{products: int, orders: int} */
    public function preview(): array
    {
        return ['products' => Product::withTrashed()->count(), 'orders' => count($this->linkedOrderIds())];
    }

    /** @return array{deleted: int, orders_deleted: int} */
    public function purge(bool $withOrders): array
    {
        return DB::transaction(function () use ($withOrders) {
            $orders = $withOrders ? $this->deleteOrders($this->linkedOrderIds()) : 0;

            // Fichiers téléversés d'abord (les images externes n'ont rien à effacer) ; les lignes d'images partent avec les produits.
            ProductImage::query()->each(fn (ProductImage $image) => $image->deleteFile());

            return ['deleted' => Product::withTrashed()->toBase()->delete(), 'orders_deleted' => $orders];
        });
    }

    /** @return list<int> commandes ayant au moins une ligne sur un produit du catalogue */
    private function linkedOrderIds(): array
    {
        return DB::table('order_lines')
            ->whereIn('product_id', DB::table('products')->select('id'))
            ->distinct()
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param  list<int|string>  $values */
    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->whereIn($column, $values)->delete();
        }
    }

    /** @param  list<int>  $orderIds */
    private function deleteOrders(array $orderIds): int
    {
        foreach (array_chunk($orderIds, 500) as $ids) {
            $invoices = DB::table('invoices')->whereIn('order_id', $ids)->pluck('id')->all();
            DB::table('invoice_lines')->whereIn('invoice_id', $invoices)->delete();
            DB::table('credit_notes')->where(fn ($q) => $q->whereIn('invoice_id', $invoices)->orWhereIn('order_id', $ids))->delete();
            DB::table('invoices')->whereIn('id', $invoices)->delete();

            DB::table('refunds')->whereIn('order_id', $ids)->delete();
            DB::table('payments')->whereIn('order_id', $ids)->delete();

            $claims = DB::table('claims')->whereIn('order_id', $ids)->pluck('id')->all();
            $photos = DB::table('claim_photos')->whereIn('claim_id', $claims)->pluck('path')->all();
            if ($photos !== []) {
                Storage::disk((string) config('claims.disk'))->delete($photos);
            }
            DB::table('claims')->whereIn('id', $claims)->delete(); // lignes et photos : suppression en cascade

            DB::table('pick_lists')->whereIn('order_id', $ids)->delete(); // lignes de préparation : en cascade
            DB::table('deliveries')->whereIn('order_id', $ids)->delete(); // dernière position : en cascade
            DB::table('slot_bookings')->whereIn('order_id', $ids)->delete();
            $this->deleteWhereIn('pos_sales', 'order_id', $ids); // module facultatif : table absente tolérée
            DB::table('stock_reservations')->whereIn('reference', array_map(fn (int $id) => "order:{$id}", $ids))->delete();

            DB::table('orders')->whereIn('id', $ids)->delete(); // lignes et historique : en cascade
        }

        return count($orderIds);
    }
}
