<?php

namespace App\Concerns;

use App\Models\customer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recherche de clients par nom ou téléphone (4 caractères minimum), pour les modals de sélection d'un client.
 */
trait SearchesCustomers
{
    public string $customerSearch = '';

    /** @return Collection<int, customer> */
    public function getCustomerResults(): Collection
    {
        $term = trim($this->customerSearch);

        if (mb_strlen($term) < 4) {
            return new Collection;
        }

        return customer::query()
            ->where(fn ($query) => $query
                ->where('firstname', 'like', '%'.$term.'%')
                ->orWhere('lastname', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhere('cellphone', 'like', '%'.$term.'%'))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(8)
            ->get();
    }
}
