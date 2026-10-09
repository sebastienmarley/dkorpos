<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Prénom et nom normalisés dans la colonne `search_name` (minuscules, sans accents), recalculés à chaque
 * enregistrement, pour une recherche par nom indépendante de la casse et des accents.
 */
trait HasSearchName
{
    public static function bootHasSearchName(): void
    {
        static::saving(function (self $model): void {
            $model->search_name = self::normalizeForSearch($model->firstname.' '.$model->lastname);
        });
    }

    /**
     * Texte en minuscules, sans accents et sans espaces superflus (é → e, À → a, ç → c).
     */
    public static function normalizeForSearch(string $value): string
    {
        return Str::squish(Str::lower(Str::ascii($value)));
    }

    /**
     * Enregistrements dont le nom contient chacun des mots cherchés, peu importe l'ordre, la casse et les accents.
     *
     * @param  Builder<static>  $query
     */
    public function scopeMatchingName(Builder $query, string $term): void
    {
        foreach (array_filter(explode(' ', self::normalizeForSearch($term))) as $word) {
            $query->where('search_name', 'like', '%'.addcslashes($word, '%_\\').'%');
        }
    }
}
