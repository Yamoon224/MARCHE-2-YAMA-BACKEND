<?php

use App\Modules\Invoicing\Domain\VatCalculator;

it('extracts VAT from a gross amount in integers', function (int $gross, int $rateBp, int $expectedVat) {
    expect((new VatCalculator)->vatIncluded($gross, $rateBp))->toBe($expectedVat);
})->with([
    'zero rate' => [5000, 0, 0],
    '18 % on 11 800' => [11800, 1800, 1800],
    '18 % on 1 000 (rounds net to 847)' => [1000, 1800, 153],
    '9 % on 10 900' => [10900, 900, 900],
    'zero amount' => [0, 1800, 0],
    'tiny amount' => [1, 1800, 0],
]);

it('always splits gross into net + vat without losing a unit', function (int $gross) {
    $calculator = new VatCalculator;
    $vat = $calculator->vatIncluded($gross, 1800);

    expect($vat)->toBeGreaterThanOrEqual(0)->and($vat)->toBeLessThanOrEqual($gross);
})->with([1, 7, 99, 100, 999, 1234, 98765, 1_000_000]);
