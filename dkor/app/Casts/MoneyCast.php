<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Colonne entière en cents ↔ Money (ADR 0008). À l'écriture, seul un Money (ou null) est accepté : un float ou un
 * nombre en dollars doit d'abord passer par Money::of() ou Money::parse().
 *
 * @implements CastsAttributes<Money|null, Money|null>
 */
class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            throw new InvalidArgumentException("La colonne {$key} doit contenir des cents entiers, pas « ".var_export($value, true).' ».');
        }

        return Money::of((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => $this->ensureMoney($key, $value)?->cents()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value instanceof Money ? $value->cents() : null;
    }

    /**
     * L'analyse statique vérifie les affectations directes ($model->total = …), mais pas les affectations en masse
     * (fill, create, update) : la valeur est donc revérifiée ici.
     *
     * @throws InvalidArgumentException si la valeur n'est ni un Money ni null.
     */
    private function ensureMoney(string $key, mixed $value): ?Money
    {
        if ($value !== null && ! $value instanceof Money) {
            throw new InvalidArgumentException("La colonne {$key} attend un Money, pas « ".get_debug_type($value).' ».');
        }

        return $value;
    }
}
