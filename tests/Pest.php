<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/Helpers/payments.php';
require_once __DIR__.'/Helpers/fulfillment.php';
require_once __DIR__.'/Helpers/accounting.php';
require_once __DIR__.'/Helpers/courier.php';
require_once __DIR__.'/Helpers/claims.php';

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Unit/Inventory');
pest()->extend(TestCase::class)->in('Architecture');

/** Oublie l'utilisateur authentifié par un actingAs précédent : la requête suivante est anonyme. */
function asGuest(): void
{
    app('auth')->forgetGuards();
}

/** Approvisionne le premier entrepôt actif (créé si besoin) avec un lot valide du produit. */
function stockUp(Product $product, int $quantity, ?string $expiresOn = null): StockLot
{
    $warehouse = Warehouse::where('is_active', true)->orderBy('id')->first() ?? Warehouse::factory()->create();

    return StockLot::factory()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity_on_hand' => $quantity,
        'expires_on' => $expiresOn ?? now()->addDays(10)->toDateString(),
    ]);
}
