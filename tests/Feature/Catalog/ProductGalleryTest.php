<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\OrderLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function galleryManager(): User
{
    return User::factory()->role(Role::CatalogManager)->create();
}

function galleryProductPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::factory()->create()->id,
        'sku' => 'SKU-GAL-1',
        'name' => 'Riz parfumé',
        'slug' => 'riz-parfume',
        'sale_unit' => 'piece',
        'price_minor' => 1250,
    ], $overrides);
}

it('creates, shows and updates a product through the admin API', function () {
    $this->actingAs(galleryManager(), 'sanctum');

    $id = $this->postJson('/api/v1/catalog/products', galleryProductPayload())->assertCreated()
        ->assertJsonPath('data.images', [])->assertJsonPath('data.cover', null)->json('data.id');

    $this->getJson("/api/v1/catalog/products/{$id}")->assertOk()->assertJsonPath('data.name', 'Riz parfumé');
    $this->putJson("/api/v1/catalog/products/{$id}", ['name' => 'Riz long', 'price_minor' => 1400])
        ->assertOk()->assertJsonPath('data.name', 'Riz long')->assertJsonPath('data.price_minor', 1400);
    $this->postJson('/api/v1/catalog/products', galleryProductPayload())->assertUnprocessable();
});

it('forbids product management to customers', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');
    $product = Product::factory()->create();

    $this->postJson('/api/v1/catalog/products', galleryProductPayload())->assertForbidden();
    $this->deleteJson("/api/v1/catalog/products/{$product->id}")->assertForbidden();
    $this->postJson("/api/v1/catalog/products/{$product->id}/images")->assertForbidden();
});

it('uploads several images, the first one becoming the cover', function () {
    Storage::fake('public');
    $this->actingAs(galleryManager(), 'sanctum');
    $product = Product::factory()->create();

    $response = $this->post("/api/v1/catalog/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png'), UploadedFile::fake()->image('c.webp')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $response->assertJsonCount(3, 'data.images')
        ->assertJsonPath('data.images.0.is_cover', true)
        ->assertJsonPath('data.images.1.is_cover', false);
    expect($response->json('data.cover'))->toBe($response->json('data.images.0.url'))
        ->and($response->json('data.image_url'))->toBe($response->json('data.cover'));

    foreach (ProductImage::all() as $image) {
        Storage::disk('public')->assertExists($image->path);
    }
});

it('exposes the gallery on the public catalogue', function () {
    Storage::fake('public');
    $this->actingAs(galleryManager(), 'sanctum');
    $product = Product::factory()->create(['slug' => 'pain']);
    $this->post("/api/v1/catalog/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]], ['Accept' => 'application/json'])->assertCreated();

    $this->getJson('/api/v1/products/pain')->assertOk()->assertJsonCount(2, 'data.images')->assertJsonPath('data.images.0.is_cover', true);
    $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.images.1.is_cover', false);
});

it('rejects non-image files and more than ten images', function () {
    Storage::fake('public');
    $this->actingAs(galleryManager(), 'sanctum');
    $product = Product::factory()->create();

    $this->post("/api/v1/catalog/products/{$product->id}/images", ['images' => [UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')]], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post("/api/v1/catalog/products/{$product->id}/images", ['images' => [UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')]], ['Accept' => 'application/json'])->assertUnprocessable();

    $files = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, $n));
    $this->post("/api/v1/catalog/products/{$product->id}/images", ['images' => $files(8)], ['Accept' => 'application/json'])->assertCreated();
    $this->post("/api/v1/catalog/products/{$product->id}/images", ['images' => $files(3)], ['Accept' => 'application/json'])->assertUnprocessable();
    expect($product->images()->count())->toBe(8);
});

it('reorders the gallery, changes the cover and deletes an image with its file', function () {
    Storage::fake('public');
    $this->actingAs(galleryManager(), 'sanctum');
    $product = Product::factory()->create();
    $response = $this->post("/api/v1/catalog/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')]], ['Accept' => 'application/json'])->assertCreated();
    [$a, $b, $c] = array_column($response->json('data.images'), 'id');

    $this->putJson("/api/v1/catalog/products/{$product->id}/images/order", ['ids' => [$c, $a, $b]])
        ->assertOk()->assertJsonPath('data.images.0.id', $c)->assertJsonPath('data.images.2.id', $b);
    $this->putJson("/api/v1/catalog/products/{$product->id}/images/order", ['ids' => [$c, $a]])->assertUnprocessable();

    $cover = $this->postJson("/api/v1/catalog/products/{$product->id}/images/{$b}/cover")->assertOk();
    expect(collect($cover->json('data.images'))->where('is_cover', true)->pluck('id')->all())->toBe([$b]);

    $path = ProductImage::find($b)->path;
    $after = $this->deleteJson("/api/v1/catalog/products/{$product->id}/images/{$b}")->assertOk();
    Storage::disk('public')->assertMissing($path);
    // La couverture supprimée est remplacée par la première image restante.
    expect(collect($after->json('data.images'))->where('is_cover', true))->toHaveCount(1)->and($after->json('data.images'))->toHaveCount(2);

    $other = Product::factory()->create();
    $this->deleteJson("/api/v1/catalog/products/{$other->id}/images/{$a}")->assertNotFound();
});

it('keeps the legacy image_url as the first gallery image', function () {
    $this->actingAs(galleryManager(), 'sanctum');

    $response = $this->postJson('/api/v1/catalog/products', galleryProductPayload(['image_url' => 'https://cdn.example.com/riz.jpg']))->assertCreated();

    $response->assertJsonCount(1, 'data.images')->assertJsonPath('data.cover', 'https://cdn.example.com/riz.jpg')
        ->assertJsonPath('data.image_url', 'https://cdn.example.com/riz.jpg');
});

it('soft deletes a product: hidden from the public catalogue, listed on demand, restorable', function () {
    $this->actingAs(galleryManager(), 'sanctum');
    $product = Product::factory()->create(['slug' => 'lait']);

    $this->deleteJson("/api/v1/catalog/products/{$product->id}")->assertNoContent();

    $this->getJson('/api/v1/products/lait')->assertNotFound();
    $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/catalog/products')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/catalog/products?trashed=with')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/catalog/products?trashed=only')->assertJsonCount(1, 'data')->assertJsonPath('data.0.deleted_at', fn ($v) => $v !== null);
    $this->getJson('/api/v1/catalog/products?trashed=nope')->assertUnprocessable();
    $this->getJson("/api/v1/catalog/products/{$product->id}")->assertOk();
    $this->assertSoftDeleted('products', ['id' => $product->id]);

    $this->postJson("/api/v1/catalog/products/{$product->id}/restore")->assertOk()->assertJsonPath('data.deleted_at', null);
    $this->getJson('/api/v1/products/lait')->assertOk();
    $this->postJson("/api/v1/catalog/products/{$product->id}/restore")->assertNotFound();
});

it('refuses to order a deleted product but keeps past order lines readable', function () {
    $ctx = awaitingPaymentOrder();
    $orderId = $ctx['orderId'];
    $line = OrderLine::where('order_id', $orderId)->firstOrFail();

    $this->actingAs(galleryManager(), 'sanctum')->deleteJson("/api/v1/catalog/products/{$line->product_id}")->assertNoContent();

    $this->actingAs($ctx['customer'], 'sanctum')->getJson("/api/v1/orders/{$orderId}")
        ->assertOk()->assertJsonPath('data.lines.0.product_id', $line->product_id)->assertJsonPath('data.lines.0.name', $line->name);
    $this->actingAs($ctx['customer'], 'sanctum')->postJson('/api/v1/orders', ['items' => [['product_id' => $line->product_id, 'quantity' => 1]]])->assertUnprocessable();
});

it('refuses to delete a category that only holds deleted products', function () {
    $this->actingAs(galleryManager(), 'sanctum');
    $product = Product::factory()->create();
    $product->delete();

    $this->deleteJson("/api/v1/catalog/categories/{$product->category_id}")->assertUnprocessable();
});
