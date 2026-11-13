<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use Immutable;

    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'account_code', 'debit_minor', 'credit_minor'];

    protected function casts(): array
    {
        return ['debit_minor' => 'integer', 'credit_minor' => 'integer'];
    }
}
