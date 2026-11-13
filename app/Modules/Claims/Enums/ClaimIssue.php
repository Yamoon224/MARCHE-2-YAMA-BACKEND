<?php

declare(strict_types=1);

namespace App\Modules\Claims\Enums;

enum ClaimIssue: string
{
    case Missing = 'missing';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case NonConforming = 'non_conforming';

    /** Un article manquant ne se photographie pas ; les autres cas exigent une preuve. */
    public function requiresPhoto(): bool
    {
        return $this !== self::Missing;
    }
}
