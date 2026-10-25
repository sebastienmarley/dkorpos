<?php

namespace App\Concerns;

use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;

/**
 * Page Livewire dont le contenu dépend du magasin choisi (`$storeId`, ADR 0013). Le magasin d'appartenance est
 * choisi par défaut; un autre magasin ne peut l'être qu'avec stores.all. Un magasin inaccessible (lien modifié,
 * requête trafiquée) est ramené au magasin par défaut.
 *
 * @property string $storeId
 */
trait ChoosesStore
{
    /**
     * Magasins offerts dans le sélecteur, déjà limités à ceux de l'usager (Store::accessibleTo()).
     *
     * @return Collection<int, Store>
     */
    abstract public function getStores(): Collection;

    public function mountChoosesStore(): void
    {
        $this->keepAccessibleStore();
    }

    public function updatedChoosesStore(string $property): void
    {
        if ($property === 'storeId') {
            $this->keepAccessibleStore();
        }
    }

    /**
     * Indique si l'usager peut choisir un autre magasin que le sien.
     */
    public function canChooseStore(): bool
    {
        return auth()->user()->hasAllStores();
    }

    protected function keepAccessibleStore(): void
    {
        $stores = $this->getStores();

        if ($this->storeId !== '' && $stores->contains('id', (int) $this->storeId)) {
            return;
        }

        $userStoreId = auth()->user()->store_id;

        $this->storeId = (string) ($stores->contains('id', $userStoreId) ? $userStoreId : ($stores->first()->id ?? ''));
    }
}
