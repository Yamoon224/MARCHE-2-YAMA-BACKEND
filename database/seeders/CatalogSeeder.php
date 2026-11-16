<?php

namespace Database\Seeders;

use App\Modules\Catalog\Enums\SaleUnit;
use App\Modules\Catalog\Models\Banner;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Recipe;
use Illuminate\Database\Seeder;

/** Catalogue de démonstration (développement et recette) : rayons, produits, bannières, recettes. */
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
            $categories[$slug] = Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'position' => $position]);
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
            $products[$sku] = Product::updateOrCreate(['sku' => $sku], [
                'category_id' => $categories[$category]->id, 'name' => $name, 'slug' => $slug, 'sale_unit' => $unit,
                'is_weight_variable' => $unit === SaleUnit::Kilogram, 'price_minor' => $price,
                'compare_at_price_minor' => $oldPrice, 'perishable' => $perishable, 'is_featured' => $featured,
            ]);
        }

        foreach ([
            ['Les meilleurs produits, aux meilleurs prix', 'Vos courses livrées à domicile à Yama, ou à retirer en magasin.', 'Acheter maintenant', '/rayons'],
            ['Du frais, tous les jours', 'Fruits, légumes, viandes et poissons suivis par lot et date limite.', 'Voir les rayons', '/rayons/fruits-legumes'],
            ['Cuisinez comme chez nous', 'Tous les ingrédients d\'un plat, en un clic.', 'Découvrir les recettes', '/#recettes'],
        ] as $position => [$title, $text, $label, $url]) {
            Banner::updateOrCreate(['title' => $title], ['text' => $text, 'cta_label' => $label, 'cta_url' => $url, 'position' => $position]);
        }

        // [slug, nom, icône, [sku => quantité en unité de base (pièces, g ou ml)]]
        foreach ([
            ['kedjenou-de-poulet', 'Kèdjenou de poulet', '🍗', ['BP-001' => 1500, 'FL-001' => 500, 'FL-003' => 300, 'FL-004' => 100]],
            ['alloco-poisson-braise', 'Alloco et poisson braisé', '🍌', ['FL-002' => 1000, 'BP-002' => 800, 'FL-003' => 300, 'EP-002' => 1]],
            ['attieke-poisson', 'Attiéké et poisson', '🥣', ['EP-003' => 2, 'BP-002' => 800, 'FL-001' => 400, 'FL-003' => 300]],
        ] as $position => [$slug, $name, $icon, $ingredients]) {
            $recipe = Recipe::updateOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'position' => $position]);
            foreach ($ingredients as $sku => $quantity) {
                $recipe->items()->updateOrCreate(['product_id' => $products[$sku]->id], ['quantity' => $quantity]);
            }
        }
    }
}
