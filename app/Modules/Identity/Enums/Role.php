<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case CatalogManager = 'catalog_manager';
    case StockKeeper = 'stock_keeper';
    case Picker = 'picker';
    case OrderOperator = 'order_operator';
    case Cashier = 'cashier';
    case Buyer = 'buyer';
    case Accountant = 'accountant';
    case Courier = 'courier';
    case Support = 'support';
    case Marketing = 'marketing';

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::CatalogManager => ['catalog.manage'],
            self::StockKeeper => ['inventory.manage', 'inventory.view'],
            self::Buyer => ['inventory.view'],
            self::Picker => ['inventory.view', 'orders.prepare'],
            self::OrderOperator => ['orders.view', 'orders.manage', 'orders.prepare', 'delivery.manage', 'delivery.assign'],
            self::Courier => ['orders.deliver', 'delivery.courier'],
            self::Support => ['orders.view', 'invoicing.view', 'payments.refund'],
            self::Accountant => ['orders.view', 'invoicing.view', 'accounting.view'],
            default => [],
        };
    }

    public function can(string $permission): bool
    {
        $granted = $this->permissions();

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }
}
