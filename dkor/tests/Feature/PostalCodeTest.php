<?php

use App\Rules\PostalCode;
use Illuminate\Support\Facades\Validator;

function postalCodePasses(?string $country, string $postalCode): bool
{
    return Validator::make(['postal_code' => $postalCode], ['postal_code' => [new PostalCode($country)]])->passes();
}

it('accepte un code postal canadien A1A1A1 et refuse le reste', function (string $postalCode, bool $passes) {
    expect(postalCodePasses('CA', $postalCode))->toBe($passes);
})->with([
    ['H2X1Y4', true],
    ['PATATE', false],
    ['H2X 1Y4', false],
    ['h2x1y4', false],
    ['12345', false],
]);

it('accepte un ZIP Code de 5 chiffres aux États-Unis', function (string $postalCode, bool $passes) {
    expect(postalCodePasses('US', $postalCode))->toBe($passes);
})->with([
    ['12345', true],
    ['1234', false],
    ['H2X1Y4', false],
]);
