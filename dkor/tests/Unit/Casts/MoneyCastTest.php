<?php

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->cast = new MoneyCast;
    $this->model = new class extends Model {};
});

it('lit des cents entiers comme un Money', function (int|string $stored) {
    expect($this->cast->get($this->model, 'total', $stored, [])->cents())->toBe(1234);
})->with([
    'entier' => 1234,
    'chaîne de chiffres (pilote PDO)' => '1234',
]);

it('lit null comme null', function () {
    expect($this->cast->get($this->model, 'total', null, []))->toBeNull();
});

it('refuse de lire une colonne qui contient encore des dollars décimaux', function (string|float $stored) {
    expect(fn () => $this->cast->get($this->model, 'total', $stored, []))->toThrow(InvalidArgumentException::class);
})->with([
    'chaîne décimale' => '12.34',
    'float' => 12.34,
]);

it('enregistre un Money en cents, et null tel quel', function () {
    expect($this->cast->set($this->model, 'total', Money::of(1234), []))->toBe(['total' => 1234])
        ->and($this->cast->set($this->model, 'total', null, []))->toBe(['total' => null]);
});

it('refuse d\'enregistrer autre chose qu\'un Money', function (mixed $value) {
    expect(fn () => $this->cast->set($this->model, 'total', $value, []))->toThrow(InvalidArgumentException::class);
})->with([
    'float en dollars' => 12.34,
    'entier' => 1234,
    'chaîne saisie' => '12.34',
]);

it('sérialise en cents', function () {
    expect($this->cast->serialize($this->model, 'total', Money::of(1234), []))->toBe(1234);
});
