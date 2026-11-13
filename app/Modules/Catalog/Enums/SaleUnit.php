<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Unité de vente. Les quantités sont stockées en entiers dans l'unité de base :
 * pièces, grammes (kg) ou millilitres (litre).
 */
enum SaleUnit: string
{
    case Piece = 'piece';
    case Kilogram = 'kg';
    case Liter = 'l';
}
