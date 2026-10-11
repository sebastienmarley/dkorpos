<?php

use App\Support\Money;

it('lit un montant saisi en dollars', function (string|int|float $input, int $cents) {
    expect(Money::parse($input)->cents())->toBe($cents);
})->with([
    'point décimal' => ['12.34', 1234],
    'virgule décimale' => ['12,34', 1234],
    'espaces des milliers' => ['1 234,50', 123450],
    'espace insécable des milliers' => ["1\u{202F}234,50", 123450],
    'symbole de dollar' => ['12 $', 1200],
    'dollars entiers' => ['12', 1200],
    'une décimale' => ['12.5', 1250],
    'sans partie entière' => ['.5', 50],
    'négatif' => ['-0.05', -5],
    'troisième décimale arrondie vers le haut' => ['0.615', 62],
    'troisième décimale arrondie vers le bas' => ['0.614', 61],
    'négatif arrondi en s\'éloignant de zéro' => ['-0.615', -62],
    'entier en dollars' => [12, 1200],
    'float en dollars' => [4.1, 410],
]);

it('refuse ce qui n\'est pas un montant', function (string $input) {
    expect(fn () => Money::parse($input))->toThrow(InvalidArgumentException::class);
})->with([
    'vide' => '',
    'texte' => 'abc',
    'deux points décimaux' => '12.34.56',
    'notation scientifique' => '1e3',
    'signe seul' => '-',
]);

it('écrit le montant pour un formulaire et pour l\'affichage', function (int $cents, string $decimal, string $formatted) {
    $money = Money::of($cents);

    expect($money->toDecimal())->toBe($decimal)
        ->and($money->format())->toBe($formatted)
        ->and((string) $money)->toBe($formatted);
})->with([
    'milliers' => [123450, '1234.50', '1,234.50 $'],
    'moins d\'un dollar' => [7, '0.07', '0.07 $'],
    'zéro' => [0, '0.00', '0.00 $'],
    'négatif' => [-5, '-0.05', '-0.05 $'],
    'millions' => [123456789, '1234567.89', '1,234,567.89 $'],
]);

it('additionne, soustrait et multiplie par une quantité exactement', function () {
    $price = Money::of(1999);

    expect($price->plus(Money::of(1))->cents())->toBe(2000)
        ->and($price->minus(Money::of(2000))->cents())->toBe(-1)
        ->and($price->times(3)->cents())->toBe(5997)
        ->and($price->negate()->cents())->toBe(-1999)
        ->and($price->negate()->abs()->cents())->toBe(1999)
        ->and(Money::sum([Money::of(10), Money::of(20), Money::of(-5)])->cents())->toBe(25)
        ->and(Money::sum([])->isZero())->toBeTrue();
});

it('applique un pourcentage arrondi une seule fois au cent, la demie en s\'éloignant de zéro', function (int $cents, string|int|float $rate, int $expected) {
    expect(Money::of($cents)->percent($rate)->cents())->toBe($expected);
})->with([
    '30 % de 2,05 $ (0,615 $, faux en float)' => [205, 30, 62],
    '15 % de 4,10 $ (0,615 $, faux en float)' => [410, 15, 62],
    'TPS sur 59,97 $' => [5997, '5', 300],
    'TVQ sur 59,97 $' => [5997, '9.975', 598],
    'taux reçu en float' => [1999, 9.975, 199],
    'escompte de 2 % sur 22,25 $' => [2225, 2, 45],
    'demi-cent' => [100, '12.5', 13],
    'montant négatif' => [-205, 30, -62],
]);

it('applique un facteur décimal arrondi au cent', function (int $cents, string|int|float $factor, int $expected) {
    expect(Money::of($cents)->multiply($factor)->cents())->toBe($expected);
})->with([
    'multiplicateur de prix' => [1234, '2.35', 2900],
    'multiplicateur entier' => [700, 2, 1400],
    'facteur à quatre décimales' => [110, '1.3333', 147],
    'facteur reçu en float' => [3333, 3.0, 9999],
]);

it('arrondit le comptant aux 0,05 $ près', function (int $cents, int $expected) {
    expect(Money::of($cents)->roundTo(5)->cents())->toBe($expected);
})->with([
    '1 ¢ vers le bas' => [2371, 2370],
    '2 ¢ vers le bas' => [2372, 2370],
    '3 ¢ vers le haut' => [2373, 2375],
    '4 ¢ vers le haut' => [2374, 2375],
    '6 ¢ vers le bas' => [2366, 2365],
    '8 ¢ vers le haut' => [2368, 2370],
    'déjà rond' => [2375, 2375],
    'négatif' => [-2373, -2375],
]);

it('refuse un pas d\'arrondi inférieur à un cent', function () {
    expect(fn () => Money::of(100)->roundTo(0))->toThrow(InvalidArgumentException::class);
});

it('compare deux montants', function () {
    $small = Money::of(100);
    $large = Money::of(200);

    expect($small->lessThan($large))->toBeTrue()
        ->and($small->lessThanOrEqual(Money::of(100)))->toBeTrue()
        ->and($large->greaterThan($small))->toBeTrue()
        ->and($large->greaterThanOrEqual(Money::of(200)))->toBeTrue()
        ->and($small->equals(Money::of(100)))->toBeTrue()
        ->and($small->min($large))->toBe($small)
        ->and($small->max($large))->toBe($large)
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of(-1)->isNegative())->toBeTrue()
        ->and(Money::of(1)->isPositive())->toBeTrue();
});

it('se sérialise en cents', function () {
    expect(json_encode(['total' => Money::of(1234)]))->toBe('{"total":1234}');
});
