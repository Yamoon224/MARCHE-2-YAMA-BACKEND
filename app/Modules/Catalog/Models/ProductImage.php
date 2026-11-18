<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property int $position
 * @property bool $is_cover
 */
class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'position', 'is_cover'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_cover' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Une image externe (historique) garde son URL ; un fichier téléversé passe par le disque public. */
    public function url(): string
    {
        return $this->isExternal() ? $this->path : Storage::disk('public')->url($this->path);
    }

    public function isExternal(): bool
    {
        return str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://');
    }

    /** Supprime le fichier téléversé (les images externes n'ont rien à nettoyer). */
    public function deleteFile(): void
    {
        if (! $this->isExternal()) {
            Storage::disk('public')->delete($this->path);
        }
    }
}
