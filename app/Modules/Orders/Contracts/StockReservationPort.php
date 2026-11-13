<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\Exceptions\InsufficientStock;
use Carbon\CarbonInterface;

/** Port : Orders pilote la réservation de stock sans connaître le module Inventory. */
interface StockReservationPort
{
    /**
     * Réserve (FEFO) toutes les quantités ou aucune. Sans date d'expiration, la réservation est maintenue.
     *
     * @param  array<int, int>  $quantities  identifiant produit => quantité en unité de base
     *
     * @throws InsufficientStock
     */
    public function reserve(string $reference, array $quantities, ?CarbonInterface $expiresAt): void;

    /** Maintient la réservation sans expiration (commande payée). */
    public function hold(string $reference): void;

    /** Libère la réservation (annulation, retour). */
    public function release(string $reference): void;

    /** Réduit la quantité réservée d'un produit (rupture, pesée plus légère) ; sans effet si elle est déjà inférieure. */
    public function shrink(string $reference, int $productId, int $newQuantity): void;

    /** Transforme la réservation en sortie physique de stock (livraison). */
    public function commit(string $reference): void;
}
