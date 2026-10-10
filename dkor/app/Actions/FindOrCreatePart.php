<?php

namespace App\Actions;

use App\Models\Part;
use App\Rules\UniqueCleanProductModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recherche et création des pièces de remplacement. La création ne fait jamais de doublon : si le fournisseur a
 * déjà une pièce de même modèle nettoyé, elle est retournée sans être modifiée (wasRecentlyCreated à false).
 */
class FindOrCreatePart
{
    public const MIN_SEARCH_LENGTH = 2;

    /**
     * Pièces dont le modèle, la description ou un produit lié contient le terme (2 caractères minimum).
     *
     * @return Collection<int, Part>
     */
    public function search(string $term, int $limit = 10): Collection
    {
        if (mb_strlen(trim($term)) < self::MIN_SEARCH_LENGTH) {
            return new Collection;
        }

        return Part::query()
            ->with('supplier')
            ->matching($term)
            ->orderBy('model')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{supplier_id: int|string, model: string, description: string, last_cost: float|string}  $attributes
     */
    public function create(array $attributes): Part
    {
        $existing = Part::query()
            ->where('supplier_id', $attributes['supplier_id'])
            ->where('clean_model', UniqueCleanProductModel::clean($attributes['model']))
            ->first();

        return $existing ?? Part::create([
            'supplier_id' => $attributes['supplier_id'],
            'model' => trim($attributes['model']),
            'description' => trim($attributes['description']),
            'last_cost' => $attributes['last_cost'],
        ]);
    }
}
