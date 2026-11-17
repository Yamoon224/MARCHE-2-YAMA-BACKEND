<?php

namespace Database\Seeders;

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Banner;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données préliminaires de la vitrine : rayons, produits, bannières, recettes. Sûr en production : ne crée que ce qui
 * manque (firstOrCreate), donc rejouer le seeder n'écrase jamais ce que le backoffice a modifié.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach ([
            ['fruits-legumes', 'Fruits et légumes', '🥬'],
            ['boucherie-poissonnerie', 'Boucherie et poissonnerie', '🥩'],
            ['frais-laitiers', 'Frais et laitiers', '🧀'],
            ['boulangerie', 'Boulangerie', '🥖'],
            ['epicerie', 'Épicerie', '🍚'],
            ['boissons', 'Boissons', '🥤'],
            ['surgeles', 'Surgelés', '🧊'],
            ['bebe', 'Bébé', '🍼'],
            ['hygiene-beaute', 'Hygiène et beauté', '🧴'],
            ['entretien-maison', 'Entretien et maison', '🧽'],
        ] as $position => [$slug, $name, $icon]) {
            $categories[$slug] = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'position' => $position]);
        }

        // Rayons mis en avant sur l'accueil (uniquement à la création : le backoffice garde la main ensuite).
        foreach ([
            'fruits-legumes' => ['Du frais, tous les jours', 'Fruits et légumes de saison.'],
            'epicerie' => ['Notre épicerie', 'Les indispensables du placard.'],
        ] as $slug => [$homeTitle, $homeSubtitle]) {
            if ($categories[$slug]->wasRecentlyCreated) {
                $categories[$slug]->update(['show_on_home' => true, 'home_title' => $homeTitle, 'home_subtitle' => $homeSubtitle]);
            }
        }

        // [rayon, sku, nom, slug, unité, prix, ancien prix (promo), périssable, mis en avant]
        $items = [
            ['fruits-legumes', 'FL-001', 'Tomates fraîches', 'tomates-fraiches', SaleUnit::Kilogram, 900, 1200, true, false],
            ['fruits-legumes', 'FL-002', 'Banane plantain', 'banane-plantain', SaleUnit::Kilogram, 700, null, true, true],
            ['fruits-legumes', 'FL-003', 'Oignons', 'oignons', SaleUnit::Kilogram, 800, null, true, true],
            ['fruits-legumes', 'FL-004', 'Piments frais', 'piments-frais', SaleUnit::Kilogram, 2500, null, true, false],
            ['fruits-legumes', 'FL-005', 'Gombo frais', 'gombo-frais', SaleUnit::Kilogram, 1800, null, true, false],
            ['boucherie-poissonnerie', 'BP-001', 'Poulet entier', 'poulet-entier', SaleUnit::Kilogram, 3800, null, true, false],
            ['boucherie-poissonnerie', 'BP-002', 'Poisson capitaine', 'poisson-capitaine', SaleUnit::Kilogram, 4500, null, true, true],
            ['frais-laitiers', 'FR-001', 'Lait entier 1 L', 'lait-entier-1l', SaleUnit::Piece, 1100, null, true, true],
            ['epicerie', 'EP-001', 'Riz parfumé 5 kg', 'riz-parfume-5kg', SaleUnit::Piece, 5500, 6200, false, true],
            ['epicerie', 'EP-002', 'Huile végétale 1 L', 'huile-vegetale-1l', SaleUnit::Piece, 1500, 1800, false, false],
            ['epicerie', 'EP-003', 'Attiéké', 'attieke', SaleUnit::Piece, 500, null, true, true],
            ['epicerie', 'EP-004', 'Concentré de tomate', 'concentre-de-tomate', SaleUnit::Piece, 350, null, false, false],
            ['boissons', 'BO-001', 'Eau minérale 1,5 L', 'eau-minerale-1-5l', SaleUnit::Piece, 400, null, false, true],
        ];

        $products = [];
        foreach ($items as [$category, $sku, $name, $slug, $unit, $price, $oldPrice, $perishable, $featured]) {
            $products[$sku] = Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $categories[$category]->id, 'name' => $name, 'slug' => $slug, 'sale_unit' => $unit,
                'is_weight_variable' => $unit === SaleUnit::Kilogram, 'price_minor' => self::cents($price),
                'compare_at_price_minor' => $oldPrice === null ? null : self::cents($oldPrice), 'perishable' => $perishable, 'is_featured' => $featured,
            ]);
        }

        $this->seedLargeCatalog($categories, array_column($items, 3));

        foreach ([
            ['Les meilleurs produits, aux meilleurs prix', 'Vos courses livrées à Paris et en Île-de-France, ou à retirer en magasin.', 'Acheter maintenant', '/rayons'],
            ['Du frais, tous les jours', 'Fruits, légumes, viandes et poissons suivis par lot et date limite.', 'Voir les rayons', '/rayons/fruits-legumes'],
            ['Cuisinez comme chez nous', 'Tous les ingrédients d\'un plat, en un clic.', 'Découvrir les recettes', '/#combos'],
        ] as $position => [$title, $text, $label, $url]) {
            Banner::firstOrCreate(['title' => $title], ['eyebrow' => 'Authentique & savoureux', 'text' => $text, 'cta_label' => $label, 'cta_url' => $url, 'position' => $position]);
        }

        // [slug, nom, icône, [sku => quantité en unité de base (pièces, g ou ml)]]
        foreach ([
            ['kedjenou-de-poulet', 'Kèdjenou de poulet', '🍗', ['BP-001' => 1500, 'FL-001' => 500, 'FL-003' => 300, 'FL-004' => 100]],
            ['alloco-poisson-braise', 'Alloco et poisson braisé', '🍌', ['FL-002' => 1000, 'BP-002' => 800, 'FL-003' => 300, 'EP-002' => 1]],
            ['attieke-poisson', 'Attiéké et poisson', '🥣', ['EP-003' => 2, 'BP-002' => 800, 'FL-001' => 400, 'FL-003' => 300]],
        ] as $position => [$slug, $name, $icon, $ingredients]) {
            $recipe = Recipe::firstOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'position' => $position]);
            foreach ($ingredients as $sku => $quantity) {
                $recipe->items()->firstOrCreate(['product_id' => $products[$sku]->id], ['quantity' => $quantity]);
            }
        }
    }

    /** Prix de référence -> centimes d'euro (÷ 4), arrondis à 5 centimes, 10 centimes minimum. */
    private static function cents(float|int $reference): int
    {
        return max(10, (int) (round($reference / 4 / 5) * 5));
    }

    /**
     * Catalogue étendu (plusieurs centaines de produits) généré depuis data/catalog_products.php.
     * SKU et slugs sont déterministes ; un produit déjà présent (même SKU) n'est jamais modifié.
     *
     * @param  array<string, Category>  $categories
     * @param  list<string>  $takenSlugs
     */
    private function seedLargeCatalog(array $categories, array $takenSlugs): void
    {
        $prefixes = [
            'fruits-legumes' => 'FL', 'boucherie-poissonnerie' => 'BP', 'frais-laitiers' => 'FR', 'boulangerie' => 'BL', 'epicerie' => 'EP',
            'boissons' => 'BO', 'surgeles' => 'SU', 'bebe' => 'BB', 'hygiene-beaute' => 'HB', 'entretien-maison' => 'EM',
        ];
        $units = ['kg' => SaleUnit::Kilogram, 'l' => SaleUnit::Liter, 'piece' => SaleUnit::Piece];
        $slugs = array_flip($takenSlugs);
        $index = 0;

        foreach (require __DIR__.'/data/catalog_products.php' as $categorySlug => $entries) {
            $sequence = 100;

            foreach ($entries as $entry) {
                [$name, $unit, $basePrice, $perishable] = $entry;

                foreach ($entry[4] ?? [['', 1]] as [$label, $multiplier]) {
                    $fullName = trim($name.' '.$label);
                    $slug = Str::slug($fullName);
                    $index++;

                    if (isset($slugs[$slug])) {
                        continue;
                    }
                    $slugs[$slug] = true;

                    $price = self::cents($basePrice * $multiplier);

                    Product::firstOrCreate(['sku' => $prefixes[$categorySlug].'-'.$sequence++], [
                        'category_id' => $categories[$categorySlug]->id, 'name' => $fullName, 'slug' => $slug, 'sale_unit' => $units[$unit],
                        'is_weight_variable' => $unit === 'kg', 'price_minor' => $price,
                        'compare_at_price_minor' => $index % 11 === 0 ? (int) (ceil($price * 1.2 / 10) * 10) : null,
                        'perishable' => $perishable, 'is_featured' => $index % 9 === 0,
                    ]);
                }
            }
        }
    }
}
