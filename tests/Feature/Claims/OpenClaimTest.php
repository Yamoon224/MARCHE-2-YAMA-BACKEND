<?php

use App\Modules\Claims\Models\Claim;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->milk = pieceProduct(1000, 50, 'Lait');
    $this->tomatoes = weightProduct(900, 100_000, 'Tomates');
    $this->ctx = deliveredOrder([['product' => $this->milk, 'quantity' => 3], ['product' => $this->tomatoes, 'quantity' => 2000]]);
    $this->milkLine = $this->ctx['lines'][$this->milk->id];
    $this->tomatoLine = $this->ctx['lines'][$this->tomatoes->id];
});

it('opens a claim with a photo: the order goes into claim and the ceiling is the value of what was claimed', function () {
    $response = openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged', 2, 'Briques percées')], [photo()], 'Carton écrasé')
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.claimable_minor', 2000) // 2 briques sur 3 à 1 000
        ->assertJsonPath('data.claimable_minor', 2000)
        ->assertJsonCount(1, 'data.photos');

    expect(Order::find($this->ctx['order']->id)->status)->toBe(OrderStatus::Claim)
        ->and(Storage::disk('local')->allFiles('claims'))->toHaveCount(1)
        ->and($response->getContent())->not->toContain('claims/'); // le chemin de stockage ne sort jamais
});

it('values a weighed article proportionally to the grams claimed', function () {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->tomatoLine, 'expired', 500)], [photo()])
        ->assertCreated()->assertJsonPath('data.items.0.claimable_minor', 450); // 500 g sur 2 kg à 900/kg
});

it('uses what the customer really received after preparation as the ceiling', function () {
    // La préparation n'a servi que 1 200 g sur 2 000 : on ne peut pas réclamer plus que ce qui a été livré.
    Order::find($this->ctx['order']->id)->lines()->whereKey($this->tomatoLine)->update(['final_quantity' => 1200, 'final_line_total_minor' => 1080]);

    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->tomatoLine, 'expired', 1300)], [photo()])->assertUnprocessable();
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->tomatoLine, 'expired', 1200)], [photo()])
        ->assertCreated()->assertJsonPath('data.items.0.claimable_minor', 1080);
});

it('needs no photo for a missing article, but requires one for any other problem', function () {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'missing', 1)])->assertCreated();
});

it('requires a photo for damaged, expired or non conforming articles', function (string $issue) {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, $issue)])
        ->assertUnprocessable()->assertJsonPath('errors.claim.0', 'Une photo est obligatoire pour un produit abîmé, périmé ou non conforme.');

    expect(Claim::count())->toBe(0)->and(Order::find($this->ctx['order']->id)->status)->toBe(OrderStatus::Delivered);
})->with(['damaged', 'expired', 'non_conforming']);

it('requires a photo as soon as one of several items needs it', function () {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'missing'), claimItem($this->tomatoLine, 'damaged', 100)])->assertUnprocessable();
});

it('refuses a quantity above what was received, and unknown or duplicated lines', function () {
    $id = $this->ctx['order']->id;
    $c = $this->ctx['customer'];

    openClaim($c, $id, [claimItem($this->milkLine, 'missing', 4)])->assertUnprocessable()->assertJsonPath('errors.claim.0', 'Quantité supérieure à celle reçue pour « Lait » (maximum 3).');
    openClaim($c, $id, [claimItem(999_999, 'missing', 1)])->assertUnprocessable();
    openClaim($c, $id, [claimItem($this->milkLine, 'missing', 1), claimItem($this->milkLine, 'missing', 1)])->assertUnprocessable();
    openClaim($c, $id, [claimItem($this->milkLine, 'missing', 0)])->assertUnprocessable();

    expect(Claim::count())->toBe(0);
});

it('refuses a claim on an order that is not delivered', function () {
    $pending = awaitingPaymentOrder();
    $line = Order::find($pending['orderId'])->lines()->firstOrFail()->id;

    openClaim($pending['customer'], $pending['orderId'], [claimItem($line, 'missing')])
        ->assertUnprocessable()->assertJsonPath('errors.claim.0', "Seule une commande livrée peut faire l'objet d'une réclamation.");
});

it('hides other customers\' orders and unknown orders behind a 404', function () {
    openClaim(User::factory()->create(), $this->ctx['order']->id, [claimItem($this->milkLine, 'missing')])->assertNotFound();
    openClaim($this->ctx['customer'], 999_999, [claimItem($this->milkLine, 'missing')])->assertNotFound();

    asGuest();
    $this->post('/api/v1/orders/'.orderUuid($this->ctx['order']->id).'/claims', [], ['Accept' => 'application/json'])->assertUnauthorized();
    expect(Claim::count())->toBe(0);
});

it('accepts a claim inside the window and refuses it after', function () {
    $this->travel(47)->hours();
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'missing')])->assertCreated();
});

it('refuses a claim once the window has passed', function () {
    $this->travel(49)->hours();

    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'missing')])
        ->assertUnprocessable()->assertJsonPath('errors.claim.0', 'Le délai de réclamation (48 h après la livraison) est dépassé.');
});

it('allows a single claim per order', function () {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'missing')])->assertCreated();

    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->tomatoLine, 'missing', 100)])
        ->assertUnprocessable()->assertJsonPath('errors.claim.0', 'Une réclamation existe déjà pour cette commande.');
    expect(Claim::count())->toBe(1);
});

// ───────────────────────── photos ─────────────────────────

/** Vrai fichier temporaire : contrairement à UploadedFile::fake(), son type est déduit de son CONTENU, comme en production. */
function disguisedFile(string $content, string $clientName): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'claim');
    file_put_contents($path, $content);

    return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
}

it('refuses files that are not real images, whatever their extension or declared type', function () {
    $files = [
        disguisedFile('%PDF-1.4
1 0 obj << >> endobj
trailer << >>
%%EOF', 'preuve.jpg'),
        disguisedFile('<?php echo "pwned"; ?>', 'preuve.png'),
        disguisedFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'preuve.webp'),
        disguisedFile('juste du texte', 'preuve.jpeg'),
    ];

    foreach ($files as $file) {
        openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged')], [$file])
            ->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    }

    expect(Claim::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(Order::find($this->ctx['order']->id)->status)->toBe(OrderStatus::Delivered);
});

it('limits the number and the size of photos', function () {
    $many = array_map(fn ($i) => photo("p{$i}.jpg"), range(1, 6));

    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged')], $many)->assertUnprocessable()->assertJsonValidationErrors('photos');
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged')], [photo('gros.jpg', 5000)])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged')], array_map(fn ($i) => photo("p{$i}.jpg"), range(1, 5)))->assertCreated()->assertJsonCount(5, 'data.photos');
});

it('stores photos under a generated name, never the client\'s file name', function () {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged')], [photo('../../etc/passwd.jpg')])->assertCreated();

    $files = Storage::disk('local')->allFiles('claims');
    expect($files)->toHaveCount(1)->and($files[0])->not->toContain('passwd')->and($files[0])->not->toContain('..');
});

it('does not keep any file when the claim is refused', function () {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, [claimItem($this->milkLine, 'damaged', 99)], [photo()])->assertUnprocessable();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('validates the payload', function (array $items) {
    openClaim($this->ctx['customer'], $this->ctx['order']->id, $items, [photo()])->assertUnprocessable();
})->with([
    'no items' => [[]],
    'unknown issue' => [[['order_line_id' => 1, 'issue' => 'bored', 'quantity' => 1]]],
    'fractional quantity' => [[['order_line_id' => 1, 'issue' => 'missing', 'quantity' => 1.5]]],
    'missing quantity' => [[['order_line_id' => 1, 'issue' => 'missing']]],
]);
