<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['journal', 'source_type', 'source_id', 'description', 'entry_date', 'reversal_of_id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
