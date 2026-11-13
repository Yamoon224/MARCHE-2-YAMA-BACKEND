<?php

declare(strict_types=1);

namespace App\Modules\Claims\Models;

use App\Modules\Claims\Enums\ClaimIssue;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ClaimIssue $issue
 */
class ClaimItem extends Model
{
    protected $fillable = ['claim_id', 'order_line_id', 'product_id', 'name', 'issue', 'quantity', 'claimable_minor', 'note'];

    protected function casts(): array
    {
        return ['issue' => ClaimIssue::class, 'quantity' => 'integer', 'claimable_minor' => 'integer'];
    }
}
