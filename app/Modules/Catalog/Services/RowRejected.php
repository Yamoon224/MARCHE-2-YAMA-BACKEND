<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

/** Une ligne d'import refusée : ses motifs sont rapportés, les autres lignes continuent. */
final class RowRejected extends \RuntimeException
{
    /** @param  list<string>  $messages */
    public function __construct(public readonly array $messages)
    {
        parent::__construct(implode(' ', $messages));
    }
}
