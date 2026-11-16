<?php

declare(strict_types=1);

namespace App\Shared;

/** Mise en forme des montants : l'application travaille en centimes (EUR) (entiers), jamais en flottants. */
final class Money
{
    /** 1290 -> « 12,90 € ». */
    public static function eur(int $minor): string
    {
        return number_format($minor / 100, 2, ',', ' ').' €';
    }
}
