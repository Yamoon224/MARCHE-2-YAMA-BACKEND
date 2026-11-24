<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Galerie d'un produit : ajout, ordre, couverture, suppression. `products.image_url` suit toujours la couverture. */
final class ProductImageService
{
    public const MAX_IMAGES = 10;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Product
    {
        return DB::transaction(function () use ($attributes) {
            $product = Product::create($attributes);
            $this->adoptLegacyUrl($product);

            return $product->load('images');
        });
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($product, $attributes) {
            $product->update($attributes);
            $this->adoptLegacyUrl($product);
            $this->syncCover($product);

            return $product->load('images');
        });
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function add(Product $product, array $files): Product
    {
        $count = $product->images()->count();

        if ($count + count($files) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => ['Un produit ne peut pas avoir plus de '.self::MAX_IMAGES.' images.']]);
        }

        $stored = [];

        try {
            DB::transaction(function () use ($product, $files, $count, &$stored) {
                $position = (int) $product->images()->max('position') + 1;

                foreach ($files as $i => $file) {
                    $path = $file->store('catalog/products', 'public');
                    $stored[] = $path;

                    $product->images()->create([
                        'path' => $path,
                        'position' => $position + $i,
                        'is_cover' => $count === 0 && $i === 0,
                    ]);
                }

                $this->syncCover($product);
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        return $product->load('images');
    }

    /**
     * Rattache des images externes (URL) à la galerie, sans doublon ni dépassement de la limite ; la première devient la couverture si la galerie était vide.
     *
     * @param  list<string>  $urls
     */
    public function attachExternal(Product $product, array $urls): Product
    {
        return DB::transaction(function () use ($product, $urls) {
            $existing = $product->images()->pluck('path')->all();
            $position = (int) $product->images()->max('position') + 1;
            $count = count($existing);

            foreach (array_diff(array_unique($urls), $existing) as $url) {
                if ($count >= self::MAX_IMAGES) {
                    break;
                }

                $product->images()->create(['path' => $url, 'position' => $position++, 'is_cover' => $count === 0]);
                $count++;
            }

            $this->syncCover($product);

            return $product->load('images');
        });
    }

    /** @param  list<int>  $ids Identifiants des images dans le nouvel ordre (toutes les images du produit). */
    public function reorder(Product $product, array $ids): Product
    {
        $own = $product->images()->pluck('id')->all();
        sort($own);
        $given = $ids;
        sort($given);

        if ($own !== $given) {
            throw ValidationException::withMessages(['ids' => ['La liste doit contenir exactement les images du produit.']]);
        }

        DB::transaction(function () use ($product, $ids) {
            foreach (array_values($ids) as $position => $id) {
                $product->images()->whereKey($id)->update(['position' => $position]);
            }
        });

        return $product->load('images');
    }

    public function setCover(Product $product, ProductImage $image): Product
    {
        DB::transaction(function () use ($product, $image) {
            $product->images()->update(['is_cover' => false]);
            $image->update(['is_cover' => true]);
            $this->syncCover($product);
        });

        return $product->load('images');
    }

    public function remove(Product $product, ProductImage $image): Product
    {
        DB::transaction(function () use ($product, $image) {
            $image->delete();
            $this->syncCover($product);
        });

        // Le fichier n'est effacé qu'une fois la suppression validée en base.
        $image->deleteFile();

        return $product->load('images');
    }

    /** Une seule couverture (la première image si aucune), et image_url qui la reflète. */
    private function syncCover(Product $product): void
    {
        $images = $product->images()->get();
        $cover = $images->firstWhere('is_cover', true) ?? $images->first();

        foreach ($images as $image) {
            $should = $cover !== null && $image->id === $cover->id;

            if ($image->is_cover !== $should) {
                $image->update(['is_cover' => $should]);
            }
        }

        $url = $cover?->url();

        if ($product->image_url !== $url) {
            $product->forceFill(['image_url' => $url])->save();
        }
    }

    /** Ancien champ image_url : une URL fournie alors que la galerie est vide devient la première image. */
    private function adoptLegacyUrl(Product $product): void
    {
        $url = (string) $product->image_url;

        if ($url !== '' && ! $product->images()->exists()) {
            $product->images()->create(['path' => $url, 'position' => 0, 'is_cover' => true]);
        }
    }
}
