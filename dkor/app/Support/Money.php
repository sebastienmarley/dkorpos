<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Montant d'argent exact, en cents (ADR 0008). Immuable : chaque opération retourne un nouveau montant.
 *
 * Volontairement non convertible en chaîne : un montant écrit par erreur dans une colonne ou un texte échoue au lieu
 * de passer inaperçu. Pour l'afficher : format(); pour un formulaire : toDecimal().
 *
 * Les calculs sont faits en entiers. Un pourcentage ou un facteur appliqué à un montant est arrondi une seule fois
 * au cent, la demie arrondie en s'éloignant de zéro (0,615 $ → 0,62 $; -0,615 $ → -0,62 $).
 */
final class Money implements JsonSerializable
{
    private function __construct(private readonly int $cents) {}

    public static function of(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Montant saisi en dollars (« 12,34 », « 12.34 », « 1 234,5 », « 12 $ »). Au-delà de deux décimales, le montant
     * est arrondi au cent.
     *
     * @throws InvalidArgumentException si la valeur n'est pas un montant.
     */
    public static function parse(string|int|float $amount): self
    {
        if (is_int($amount)) {
            return new self($amount * 100);
        }

        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}", '$', ','], ['', '', '', '', '.'], trim((string) $amount));

        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $normalized, $matches) !== 1 || ($matches[2] === '' && ($matches[3] ?? '') === '')) {
            throw new InvalidArgumentException("« {$amount} » n'est pas un montant valide.");
        }

        [, $sign, $dollars, $decimals] = $matches + [3 => ''];

        return new self(self::fromDecimalParts($sign === '-', $dollars === '' ? '0' : $dollars, $decimals, 2));
    }

    /**
     * Somme de montants (zéro pour une liste vide).
     *
     * @param  iterable<Money>  $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $cents = 0;

        foreach ($amounts as $amount) {
            $cents += $amount->cents;
        }

        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    /** Montant en dollars, sans séparateur de milliers (« 1234.50 », « -0.05 ») : pour un champ de formulaire. */
    public function toDecimal(): string
    {
        return ($this->cents < 0 ? '-' : '').intdiv(abs($this->cents), 100).'.'.str_pad((string) (abs($this->cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Montant affiché, au format actuel des écrans (« 1,234.50 $ », « -0.05 $ »). */
    public function format(): string
    {
        return ($this->cents < 0 ? '-' : '').number_format(intdiv(abs($this->cents), 100)).'.'.str_pad((string) (abs($this->cents) % 100), 2, '0', STR_PAD_LEFT).' $';
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /** Montant multiplié par une quantité entière (exact). */
    public function times(int $quantity): self
    {
        return new self($this->cents * $quantity);
    }

    public function negate(): self
    {
        return new self(-$this->cents);
    }

    public function abs(): self
    {
        return new self(abs($this->cents));
    }

    /**
     * Pourcentage du montant (« 9.975 » pour 9,975 %), arrondi au cent.
     */
    public function percent(string|int|float $rate): self
    {
        return $this->applyFactor($rate, 2);
    }

    /**
     * Montant multiplié par un facteur décimal (ex. multiplicateur de prix, taux de change), arrondi au cent.
     */
    public function multiply(string|int|float $factor): self
    {
        return $this->applyFactor($factor, 0);
    }

    /**
     * Montant arrondi au multiple de $step cents le plus proche, la demie en s'éloignant de zéro (ex. 5 pour le
     * comptant : 1-2 ¢ vers le bas, 3-4 ¢ vers le haut).
     */
    public function roundTo(int $step): self
    {
        if ($step < 1) {
            throw new InvalidArgumentException('Le pas d\'arrondi doit être d\'au moins 1 cent.');
        }

        return new self(self::divideRounded($this->cents, $step) * $step);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function lessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->cents <= $other->cents;
    }

    public function min(self $other): self
    {
        return $this->cents <= $other->cents ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->cents >= $other->cents ? $this : $other;
    }

    /** Sérialisé en cents (valeur exacte). */
    public function jsonSerialize(): int
    {
        return $this->cents;
    }

    /**
     * Montant × facteur décimal / 10^$shift, calculé exactement en entiers puis arrondi au cent.
     */
    private function applyFactor(string|int|float $factor, int $shift): self
    {
        $normalized = trim(is_float($factor) ? self::floatToDecimal($factor) : (string) $factor);

        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $normalized, $matches) !== 1 || ($matches[2] === '' && ($matches[3] ?? '') === '')) {
            throw new InvalidArgumentException("« {$factor} » n'est pas un nombre valide.");
        }

        [, $sign, $whole, $decimals] = $matches + [3 => ''];
        $decimals = rtrim($decimals, '0');
        $numerator = (int) (($whole === '' ? '0' : $whole).$decimals) * ($sign === '-' ? -1 : 1);

        return new self(self::divideRounded($this->cents * $numerator, 10 ** (strlen($decimals) + $shift)));
    }

    /**
     * Division entière arrondie à l'entier le plus proche, la demie en s'éloignant de zéro.
     */
    private static function divideRounded(int $dividend, int $divisor): int
    {
        $quotient = intdiv(abs($dividend), $divisor);

        if (abs($dividend) % $divisor * 2 >= $divisor) {
            $quotient++;
        }

        return $dividend < 0 ? -$quotient : $quotient;
    }

    /**
     * Partie entière et décimales (chaînes de chiffres) converties en unités de 10^-$scale, arrondies au-delà.
     */
    private static function fromDecimalParts(bool $negative, string $whole, string $decimals, int $scale): int
    {
        $kept = str_pad(substr($decimals, 0, $scale), $scale, '0');
        $units = (int) $whole * 10 ** $scale + (int) $kept;

        if (strlen($decimals) > $scale && (int) $decimals[$scale] >= 5) {
            $units++;
        }

        return $negative ? -$units : $units;
    }

    /** Écriture décimale la plus courte d'un float (9.975 → « 9.975 », 1.0E-5 → « 0.00001 »). */
    private static function floatToDecimal(float $value): string
    {
        $shortest = (string) $value;

        return str_contains($shortest, 'E') ? rtrim(rtrim(sprintf('%.15F', $value), '0'), '.') : $shortest;
    }
}
