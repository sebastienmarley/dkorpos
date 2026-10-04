<?php

namespace App\Livewire\Catalog;

use App\Actions\ImportPriceListCsv;
use App\Models\PriceList;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

class PriceListShow extends Component
{
    use WithFileUploads;

    public PriceList $priceList;

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $showListModal = false;

    public string $listName = '';

    public string $listDiscount = '0';

    public bool $showImportModal = false;

    public ?int $importingId = null;

    public mixed $file = null;

    public function mount(PriceList $priceList): void
    {
        $this->priceList = $priceList->load('supplier');
        $this->startsOn = $priceList->starts_on->toDateString();
        $this->endsOn = $priceList->ends_on->toDateString();
    }

    public function save(): void
    {
        $this->authorize('price_lists.edit');
        abort_unless($this->priceList->isActive(), 403);

        if ($this->startDateLocked()) {
            $this->startsOn = $this->priceList->starts_on->toDateString();
        }

        $this->validate([
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
        ]);

        if (PriceList::supplierHasActiveListOverlapping($this->priceList->supplier_id, $this->startsOn, $this->endsOn, $this->priceList->id)) {
            $this->addError('startsOn', __('Une autre liste active de ce fournisseur couvre cette période.'));

            return;
        }

        $this->priceList->update(['starts_on' => $this->startsOn, 'ends_on' => $this->endsOn]);

        Flux::toast(text: __('Liste de prix mise à jour.'), variant: 'success');
    }

    private function startDateLocked(): bool
    {
        return $this->priceList->starts_on->lte(today());
    }

    public function archive(): void
    {
        $this->authorize('price_lists.edit');

        $this->priceList->update(['archived_at' => now()]);

        Flux::toast(text: __('Liste de prix archivée.'), variant: 'success');
    }

    public function openAddList(): void
    {
        $this->authorize('price_lists.edit');
        abort_unless($this->priceList->isActive(), 403);

        $this->resetErrorBag();
        $this->listName = '';
        $this->listDiscount = '0';
        $this->showListModal = true;
    }

    public function addList(): void
    {
        $this->authorize('price_lists.edit');
        abort_unless($this->priceList->isActive(), 403);

        $this->validate([
            'listName' => ['required', 'string', 'max:255'],
            'listDiscount' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->priceList->lists()->create(['name' => $this->listName, 'discount_percent' => $this->listDiscount]);

        $this->showListModal = false;
        Flux::toast(text: __('Liste ajoutée.'), variant: 'success');
    }

    public function deleteList(int $listId): void
    {
        $this->authorize('price_lists.edit');
        abort_unless($this->priceList->isActive(), 403);

        $this->priceList->lists()->findOrFail($listId)->delete();

        Flux::toast(text: __('Liste supprimée.'), variant: 'success');
    }

    public function openImport(int $listId): void
    {
        $this->authorize('price_lists.edit');
        abort_unless($this->priceList->isActive(), 403);

        $this->priceList->lists()->findOrFail($listId);

        $this->resetErrorBag();
        $this->file = null;
        $this->importingId = $listId;
        $this->showImportModal = true;
    }

    public function import(ImportPriceListCsv $importer): void
    {
        $this->authorize('price_lists.edit');
        abort_unless($this->priceList->isActive(), 403);

        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:51200']]);

        $list = $this->priceList->lists()->findOrFail($this->importingId);

        try {
            $result = $importer->handle($list, $this->file->getRealPath());
        } catch (InvalidArgumentException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->showImportModal = false;
        $this->importingId = null;
        $this->file = null;

        Flux::toast(
            text: ($result['pending']
                ? __(':rows lignes importées. Les produits seront mis à jour le :date.', ['rows' => $result['rows'], 'date' => $this->priceList->starts_on->toDateString()])
                : __(':rows lignes importées, :matched produits mis à jour.', $result))
                .($result['duplicates'] > 0 ? ' '.__(':duplicates modèles ignorés (déjà dans une autre liste).', $result) : ''),
            variant: 'success',
        );
    }

    public function render(): View
    {
        return view('livewire.catalog.price-list-show', [
            'startDateLocked' => $this->startDateLocked(),
            'lists' => $this->priceList->lists()->withCount('items')->orderBy('name')->get(),
            'history' => PriceList::query()
                ->where('supplier_id', $this->priceList->supplier_id)
                ->whereKeyNot($this->priceList->id)
                ->orderByDesc('starts_on')
                ->get(),
        ])->layout('layouts.app', ['title' => __('Liste de prix')]);
    }
}
