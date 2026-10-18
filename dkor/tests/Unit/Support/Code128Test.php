<?php

use App\Support\Code128;

it('encode un nombre pair de chiffres en jeu C avec sa somme de contrôle', function () {
    // Départ C (105) + 12, 34, 56 ; contrôle : (105 + 12×1 + 34×2 + 56×3) mod 103 = 44.
    expect(Code128::symbols('123456'))->toBe([105, 12, 34, 56, 44]);
});

it('encode un nombre impair de chiffres en jeu B', function () {
    // Départ B (104) + « 1 », « 2 », « 3 » (valeurs 17, 18, 19) ; contrôle : (104 + 17 + 36 + 57) mod 103 = 8.
    expect(Code128::symbols('123'))->toBe([104, 17, 18, 19, 8]);
});

it('dessine onze modules par symbole et treize pour l\'arrêt, entourés des zones de silence', function () {
    // 5 symboles × 11 + arrêt 13 + 2 zones de silence de 10 modules.
    expect(Code128::svg('123456'))->toContain('viewBox="0 0 88 40"');
});

it('refuse les caractères hors ASCII imprimable', function () {
    Code128::svg('é');
})->throws(InvalidArgumentException::class);
