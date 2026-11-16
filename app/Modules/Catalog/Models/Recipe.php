<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'image_url', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<RecipeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }
}
