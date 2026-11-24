<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Import en masse de produits. Les lignes arrivent déjà normalisées (le backoffice lit CSV, XLSX ou SQL).
 * Une ligne est rapprochée d'un produit existant par sa référence (SKU) : elle le met à jour, sinon elle le crée.
 * Une ligne invalide est rapportée sans bloquer les autres. En simulation (`$dryRun`), tout est annulé à la fin.
 */
final class ProductImporter
{
    public const MAX_ROWS = 5000;

    /** Rayon des produits dont le fichier ne donne aucune catégorie. */
    public const FALLBACK_CATEGORY = 'Autres';

    public function __construct(private readonly ProductImageService $images) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{dry_run: bool, created: int, updated: int, failed: int, categories_created: list<string>, errors: list<array{row: int, name: string, messages: list<string>}>, warnings: list<array{row: int, name: string, message: string}>}
     */
    public function import(array $rows, bool $dryRun): array
    {
        $report = ['dry_run' => $dryRun, 'created' => 0, 'updated' => 0, 'failed' => 0, 'categories_created' => [], 'errors' => [], 'warnings' => []];
        $categories = Category::query()->get()->keyBy('slug')->all();
        $seen = [];

        DB::beginTransaction();

        try {
            foreach (array_values($rows) as $index => $row) {
                $number = (int) ($row['row'] ?? $index + 1);
                $name = trim((string) ($row['name'] ?? ''));

                try {
                    $outcome = DB::transaction(function () use ($row, &$categories, &$seen, &$report) {
                        return $this->importRow($row, $categories, $seen, $report);
                    });
                } catch (RowRejected $e) {
                    $report['failed']++;
                    $report['errors'][] = ['row' => $number, 'name' => $name, 'messages' => $e->messages];

                    continue;
                } catch (QueryException) {
                    $report['failed']++;
                    $report['errors'][] = ['row' => $number, 'name' => $name, 'messages' => ['Enregistrement refusé par la base de données.']];

                    continue;
                }

                $report[$outcome['status']]++;

                foreach ($outcome['warnings'] as $warning) {
                    $report['warnings'][] = ['row' => $number, 'name' => $name, 'message' => $warning];
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Category>  $categories
     * @param  array<string, true>  $seen
     * @param  array<string, mixed>  $report
     * @return array{status: 'created'|'updated', warnings: list<string>}
     */
    private function importRow(array $row, array &$categories, array &$seen, array &$report): array
    {
        $data = $this->validated($row);
        $warnings = [];

        $slug = Str::slug($data['name']);
        $sku = $this->skuFor($data, $slug);

        if (isset($seen[$sku])) {
            throw new RowRejected(["Référence « {$sku} » déjà présente plus haut dans le fichier."]);
        }

        $product = Product::withTrashed()->where('sku', $sku)->first();

        if ($product?->trashed()) {
            throw new RowRejected(["Le produit « {$sku} » est supprimé : restaurez-le avant de le réimporter."]);
        }

        $seen[$sku] = true;
        $attributes = [];

        foreach (['barcode', 'description', 'sale_unit', 'is_weight_variable', 'price_minor', 'compare_at_price_minor', 'vat_rate_bp', 'track_stock', 'is_active'] as $key) {
            if (($data[$key] ?? null) !== null) {
                $attributes[$key] = $data[$key];
            }
        }

        if (($data['category'] ?? null) !== null) {
            $attributes['category_id'] = $this->category($data['category'], $categories, $report)->id;
        }

        if ($product === null) {
            $attributes['category_id'] ??= $this->category(self::FALLBACK_CATEGORY, $categories, $report)->id;
            $attributes += [
                'sku' => $sku,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($slug, $sku),
            ];

            if (! isset($attributes['price_minor'])) {
                // Sans prix, un produit en ligne se vendrait 0 € : il reste masqué jusqu'à ce qu'on le renseigne.
                $attributes['price_minor'] = 0;
                $attributes['is_active'] = false;
                $warnings[] = 'Prix absent : produit créé masqué à 0,00 €, à renseigner avant de le publier.';
            }

            $product = $this->images->create($attributes);
            $status = 'created';
        } else {
            $attributes['name'] = $data['name'];
            $product = $this->images->update($product, $attributes);
            $status = 'updated';
        }

        if (! empty($data['images'])) {
            $this->images->attachExternal($product, $data['images']);
        }

        return ['status' => $status, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validated(array $row): array
    {
        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:40'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:255'],
            'sale_unit' => ['nullable', Rule::enum(SaleUnit::class)],
            'is_weight_variable' => ['nullable', 'boolean'],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'compare_at_price_minor' => ['nullable', 'integer', 'min:0'],
            'vat_rate_bp' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'track_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:'.ProductImageService::MAX_IMAGES],
            'images.*' => ['string', 'url', 'max:500'],
        ], [], [
            'name' => 'nom', 'sku' => 'référence', 'barcode' => 'code-barres', 'category' => 'catégorie', 'price_minor' => 'prix',
            'compare_at_price_minor' => 'prix avant promotion', 'vat_rate_bp' => 'TVA', 'sale_unit' => 'unité', 'images' => 'images',
        ]);

        if ($validator->fails()) {
            throw new RowRejected($validator->errors()->all());
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();

        if (Str::slug((string) $data['name']) === '') {
            throw new RowRejected(['Le nom ne contient aucun caractère exploitable.']);
        }

        return $data;
    }

    /**
     * Référence du fichier ; à défaut, dérivée de l'identifiant d'origine (stable d'un import à l'autre), sinon du nom.
     *
     * @param  array<string, mixed>  $data
     */
    private function skuFor(array $data, string $slug): string
    {
        $sku = trim((string) ($data['sku'] ?? ''));

        if ($sku !== '') {
            return $sku;
        }

        $external = preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['external_id'] ?? '')) ?? '';

        return 'IMP-'.strtoupper(substr($external !== '' ? $external : sha1($slug), 0, 12));
    }

    private function uniqueSlug(string $slug, string $sku): string
    {
        $slug = Str::limit($slug, 100, '');
        $candidate = $slug;
        $n = 1;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.Str::slug($sku).($n > 1 ? '-'.$n : '');
            $n++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, mixed>  $report
     */
    private function category(string $name, array &$categories, array &$report): Category
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if (isset($categories[$slug])) {
            return $categories[$slug];
        }

        $category = Category::create(['name' => $name, 'slug' => $slug, 'position' => (int) Category::max('position') + 1]);
        $categories[$slug] = $category;
        $report['categories_created'][] = $name;

        return $category;
    }
}
