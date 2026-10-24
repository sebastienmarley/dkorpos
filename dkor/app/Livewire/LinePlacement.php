<?php

namespace App\Livewire;

use App\Models\CustomerOrderLine;
use App\Models\InventoryLocation;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Emplacement d'une ligne de commande client dans l'entrepôt (issue #96) : on le choisit dans la liste des
 * emplacements actifs du magasin de la commande, ou on balaie son code avec la douchette. Réutilisé dans la commande
 * client et dans la réception. L'emplacement habituel du produit est proposé par défaut.
 */
class LinePlacement extends Component
{
    public int $lineId;

    public string $locationId = '';

    public string $scan = '';

    public function mount(int $lineId): void
    {
        $this->lineId = $lineId;
        $line = $this->line();
        $this->locationId = (string) ($line->order->suggestedLocationFor($line)->id ?? '');
    }

    public function save(): void
    {
        $this->authorizePlacement();

        $line = $this->line();

        try {
            $line->order->placeLine($line, filled($this->locationId) ? (int) $this->locationId : null);
        } catch (DomainException $exception) {
            $this->addError('locationId', $exception->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->dispatch('line-placed', lineId: $line->id);
        Flux::toast(text: __('Emplacement enregistré.'), variant: 'success');
    }

    /** La douchette tape le code de l'emplacement et Entrée. */
    public function scanned(): void
    {
        $this->authorizePlacement();

        $line = $this->line();
        $location = $line->order->store_id === null ? null : InventoryLocation::findByCode($line->order->store_id, $this->scan);

        if ($location === null) {
            $this->addError('scan', __('Aucun emplacement actif « :code » dans ce magasin.', ['code' => trim($this->scan)]));

            return;
        }

        $this->locationId = (string) $location->id;
        $this->scan = '';
        $this->save();
    }

    /** @return Collection<int, InventoryLocation> */
    public function getLocations(): Collection
    {
        $line = $this->line();

        if ($line->order->store_id === null) {
            return new Collection;
        }

        return InventoryLocation::query()->ofStore($line->order->store_id)->selectable()->with('zone')->orderBy('code')->get();
    }

    private function line(): CustomerOrderLine
    {
        return CustomerOrderLine::query()->with(['order', 'location.zone', 'product'])->findOrFail($this->lineId);
    }

    private function authorizePlacement(): void
    {
        abort_unless(Gate::any(['customer_orders.edit', 'receptions.create']), 403);
    }

    public function render(): View
    {
        return view('livewire.line-placement', ['line' => $this->line()]);
    }
}
