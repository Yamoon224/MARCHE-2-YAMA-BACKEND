<?php

use App\Modules\Orders\Domain\LineTotalCalculator;

it('multiplies price by quantity for piece items', function () {
    expect((new LineTotalCalculator)->total('piece', 1500, 3))->toBe(4500);
});

it('prices weight items per kilo from grams', function (int $grams, int $expected) {
    // 900 F CFA / kg
    expect((new LineTotalCalculator)->total('kg', 900, $grams))->toBe($expected);
})->with([
    'one kilo' => [1000, 900],
    '250 g' => [250, 225],
    '1,5 kg' => [1500, 1350],
    'rounds half up' => [1, 1],   // 0,9 -> 1
    'rounds down' => [500, 450],
    '333 g' => [333, 300],        // 299,7 -> 300
]);

it('prices volume items per litre from millilitres', function () {
    expect((new LineTotalCalculator)->total('l', 1100, 500))->toBe(550);
});
