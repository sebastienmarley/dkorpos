<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonne time ↔ chaîne « HH:MM ». SQLite rend la valeur telle qu'enregistrée, mais PostgreSQL ajoute les secondes
 * (« 17:00:00 ») : l'heure est ramenée à « HH:MM » dans les deux sens, pour que les comparaisons et l'affichage ne
 * dépendent pas du moteur (ADR 0014).
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class TimeOfDay implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->normalize($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->normalize($value);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 5);
    }
}
