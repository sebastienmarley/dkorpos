<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Code-barres Code 128 rendu en SVG, sans dépendance.
 *
 * Un nombre de chiffres pair est encodé en jeu C (deux chiffres par symbole, plus compact);
 * le reste en jeu B (ASCII imprimable).
 */
final class Code128
{
    /**
     * Largeurs barre/espace alternées (en modules) de chaque valeur de symbole, 0 à 105, puis l'arrêt.
     *
     * @var list<string>
     */
    private const array Patterns = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const int StartB = 104;

    private const int StartC = 105;

    private const int Stop = 106;

    private const int QuietZone = 10;

    /**
     * Valeurs de symbole encodées : départ, données, somme de contrôle (l'arrêt n'est pas inclus).
     *
     * @return list<int>
     */
    public static function symbols(string $value): array
    {
        if ($value === '' || preg_match('/^[\x20-\x7E]+$/', $value) !== 1) {
            throw new InvalidArgumentException('Le Code 128 n\'accepte que des caractères ASCII imprimables.');
        }

        if (preg_match('/^(\d\d)+$/', $value) === 1) {
            $symbols = [self::StartC, ...array_map('intval', str_split($value, 2))];
        } else {
            $symbols = [self::StartB, ...array_map(fn (string $character): int => ord($character) - 32, str_split($value))];
        }

        $checksum = $symbols[0];

        foreach (array_slice($symbols, 1) as $position => $symbol) {
            $checksum += ($position + 1) * $symbol;
        }

        return [...$symbols, $checksum % 103];
    }

    /**
     * Code-barres en SVG, mis à l'échelle par CSS (une unité du viewBox = un module).
     */
    public static function svg(string $value, int $height = 40): string
    {
        $x = self::QuietZone;
        $bars = '';

        foreach ([...self::symbols($value), self::Stop] as $symbol) {
            foreach (str_split(self::Patterns[$symbol]) as $index => $width) {
                if ($index % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$width.'" height="'.$height.'"/>';
                }

                $x += (int) $width;
            }
        }

        $totalWidth = $x + self::QuietZone;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$totalWidth.' '.$height.'" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="'.e($value).'">'.$bars.'</svg>';
    }
}
