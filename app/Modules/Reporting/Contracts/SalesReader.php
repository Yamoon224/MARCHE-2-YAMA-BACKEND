<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Contracts;

use Carbon\CarbonImmutable;

/**
 * Lecture des ventes, fournie par le module Commandes. Une vente est une commande payée qui n'a été ni annulée,
 * ni remboursée, ni retournée ; son montant est le total final (après préparation) quand il existe.
 */
interface SalesReader
{
    /** @return array{orders_count: int, revenue_minor: int} */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Toutes les commandes créées sur la période, ventes ou non.
     *
     * @return list<array{status: string, count: int}>
     */
    public function ordersByStatus(CarbonImmutable $from, CarbonImmutable $to): array;

    /** @return list<array{date: string, orders_count: int, revenue_minor: int}> jours sans vente absents */
    public function byDay(CarbonImmutable $from, CarbonImmutable $to): array;

    /** @return list<array{product_id: int, name: string, quantity: int, revenue_minor: int}> triés par chiffre d'affaires décroissant */
    public function byProduct(CarbonImmutable $from, CarbonImmutable $to): array;
}
