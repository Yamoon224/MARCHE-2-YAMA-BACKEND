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
            self::CatalogManager => ['catalog.manage', 'banners.manage'],
            self::StockKeeper => ['inventory.manage', 'inventory.view'],
            self::Buyer => ['inventory.view'],
            self::Picker => ['inventory.view', 'orders.prepare'],
            self::OrderOperator => ['orders.view', 'orders.manage', 'orders.prepare', 'delivery.manage', 'delivery.assign', 'cash.receive', 'customers.view', 'reports.view'],
            self::Courier => ['orders.deliver', 'delivery.courier'],
            self::Support => ['orders.view', 'invoicing.view', 'payments.refund', 'claims.decide', 'customers.view'],
            self::Accountant => ['orders.view', 'invoicing.view', 'accounting.view', 'reports.view'],
            self::Marketing => ['promotions.manage', 'customers.view', 'banners.manage'],
            default => [],
        };
    }

    public function can(string $permission): bool
    {
        $granted = $this->permissions();

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Client',
            self::Admin => 'Administrateur',
            self::CatalogManager => 'Responsable catalogue',
            self::StockKeeper => 'Magasinier',
            self::Picker => 'Préparateur',
            self::OrderOperator => 'Opérateur de commandes',
            self::Cashier => 'Caissier',
            self::Buyer => 'Acheteur',
            self::Accountant => 'Comptable',
            self::Courier => 'Livreur',
            self::Support => 'Support client',
            self::Marketing => 'Marketing',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /** @return list<self> */
    public static function staffRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role->isStaff()));
    }
}
