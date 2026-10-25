<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Enregistrement qui appartient à un magasin (`store_id`), visible seulement par les usagers de ce magasin ou par ceux
 * qui ont accès à tous les magasins (ADR 0001).
 */
trait BelongsToStore
{
    /**
     * Limite la requête aux magasins pour lesquels l'usager peut agir. Sans stores.all, ce qui n'a pas de magasin est exclu.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function accessibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllStores()) {
            $query->whereIn($this->qualifyColumn('store_id'), array_filter([$user->store_id]));
        }
    }

    /**
     * Indique si l'usager peut voir ou agir pour cet enregistrement.
     */
    public function isAccessibleTo(User $user): bool
    {
        return $user->canActForStore($this->store_id);
    }
}
