<?php

namespace App\Livewire;

use App\Actions\FindOrCreatePart;
use App\Enums\SupplierType;
use App\Models\Part;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bouton « Ajouter une pièce » : recherche une pièce existante ou en crée une. Émet `part-selected` (id) pour
 * que la page qui l'utilise décide quoi faire de la pièce choisie.
 */
class PartPicker extends Component
{
    public bool $showModal = false;

    public bool $creating = false;

    public string $search = '';

    public string $supplierId = '';

    public string $model = '';

    public string $description = '';

    public string $lastCost = '';

    #[On('open-part-picker')]
    public function open(): void
    {
        $this->authorize('parts.view');

        $this->reset(['creating', 'search', 'supplierId', 'model', 'description', 'lastCost']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function startCreating(): void
    {
        $this->authorize('parts.create');

        $this->model = trim($this->search);
        $this->creating = true;
    }

    public function cancelCreating(): void
    {
        $this->resetErrorBag();
        $this->creating = false;
    }

    public function select(int $partId): void
    {
        $this->authorize('parts.view');

        $this->choose(Part::findOrFail($partId));
    }

    public function create(FindOrCreatePart $findOrCreatePart): void
    {
        $this->authorize('parts.create');

        $this->lastCost = filled($this->lastCost) ? number_format((float) str_replace(',', '.', $this->lastCost), 2, '.', '') : '0.00';

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'lastCost' => ['required', 'numeric', 'min:0'],
        ]);

        $part = $findOrCreatePart->create([
            'supplier_id' => $this->supplierId,
            'model' => $this->model,
            'description' => $this->description,
            'last_cost' => $this->lastCost,
        ]);

        Flux::toast(
            text: $part->wasRecentlyCreated ? __('Pièce créée.') : __('Cette pièce existe déjà chez ce fournisseur : elle a été sélectionnée.'),
            variant: $part->wasRecentlyCreated ? 'success' : 'warning',
        );

        $this->choose($part);
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::where('type', SupplierType::Product)->where('is_active', true)->orderBy('name')->get();
    }

    private function choose(Part $part): void
    {
        $this->showModal = false;
        $this->reset(['creating', 'search', 'supplierId', 'model', 'description', 'lastCost']);

        $this->dispatch('part-selected', id: $part->id);
    }

    public function render(): View
    {
        return view('livewire.part-picker', [
            'results' => $this->showModal && ! $this->creating ? app(FindOrCreatePart::class)->search($this->search) : new Collection,
            'minSearchLength' => FindOrCreatePart::MIN_SEARCH_LENGTH,
        ]);
    }
}
