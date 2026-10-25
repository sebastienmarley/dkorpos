<?php

namespace App\Livewire\Catalog;

use App\Enums\SupplierType;
use App\Models\Service;
use App\Models\ServiceSupplier;
use App\Models\Supplier;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Services extends Component
{
    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $descriptionTemplate = '';

    public bool $isInternal = false;

    public string $sellingPrice = '';

    public bool $isTaxable = true;

    public bool $isActive = true;

    /** @var array<int, array{supplier_id: int|string, cost: string, selling_price: string}> */
    public array $offers = [];

    public function openCreate(): void
    {
        $this->authorize('services.create');

        $this->reset(['editingId', 'name', 'descriptionTemplate', 'isInternal', 'sellingPrice', 'isTaxable', 'isActive']);
        $this->offers = [['supplier_id' => '', 'cost' => '0.00', 'selling_price' => '0.00']];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(Service $service): void
    {
        $this->authorize('services.edit');

        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->descriptionTemplate = $service->description_template ?? '';
        $this->isInternal = $service->is_internal;
        $this->sellingPrice = $service->selling_price?->toDecimal() ?? '';
        $this->isTaxable = $service->is_taxable;
        $this->isActive = $service->is_active;
        $this->offers = ServiceSupplier::query()->where('service_id', $service->id)->orderBy('id')->get()
            ->map(fn (ServiceSupplier $offer): array => [
                'supplier_id' => $offer->supplier_id,
                'cost' => $offer->cost->toDecimal(),
                'selling_price' => $offer->selling_price->toDecimal(),
            ])
            ->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function addOffer(): void
    {
        $this->offers[] = ['supplier_id' => '', 'cost' => '0.00', 'selling_price' => '0.00'];
    }

    public function removeOffer(int $index): void
    {
        unset($this->offers[$index]);
        $this->offers = array_values($this->offers);
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'services.edit' : 'services.create');

        if ($this->isEditingSystemService()) {
            $this->isInternal = true;
            $this->isActive = true;
        }

        $this->name = trim($this->name);
        $this->sellingPrice = str_replace(',', '.', $this->sellingPrice);
        $this->offers = array_values(array_filter(array_map(fn (array $offer): array => [
            'supplier_id' => $offer['supplier_id'],
            'cost' => str_replace(',', '.', (string) $offer['cost']),
            'selling_price' => str_replace(',', '.', (string) $offer['selling_price']),
        ], $this->offers), fn (array $offer): bool => filled($offer['supplier_id'])));

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->ignore($this->editingId)],
            'descriptionTemplate' => ['nullable', 'string', 'max:255'],
            'isInternal' => ['boolean'],
            'sellingPrice' => [Rule::requiredIf($this->isInternal), 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'isTaxable' => ['boolean'],
            'isActive' => ['boolean'],
            'offers' => [Rule::requiredIf(! $this->isInternal), 'array'],
            'offers.*.supplier_id' => ['required', 'integer', 'distinct', Rule::exists('suppliers', 'id')->whereIn('type', [SupplierType::Service->value, SupplierType::Shipping->value])],
            'offers.*.cost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'offers.*.selling_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], ['offers.required' => __('Ajoutez au moins un fournisseur pour un service externe.')]);

        DB::transaction(function (): void {
            $service = Service::query()->updateOrCreate(['id' => $this->editingId], [
                'name' => $this->name,
                'description_template' => filled($this->descriptionTemplate) ? $this->descriptionTemplate : null,
                'is_internal' => $this->isInternal,
                'selling_price' => $this->isInternal ? Money::parse($this->sellingPrice) : null,
                'is_taxable' => $this->isTaxable,
                'is_active' => $this->isActive,
            ]);

            $offers = $this->isInternal ? [] : $this->offers;

            ServiceSupplier::query()
                ->where('service_id', $service->id)
                ->whereNotIn('supplier_id', array_map(fn (array $offer): int => (int) $offer['supplier_id'], $offers))
                ->delete();

            foreach ($offers as $offer) {
                ServiceSupplier::query()->updateOrCreate(
                    ['service_id' => $service->id, 'supplier_id' => (int) $offer['supplier_id']],
                    ['cost' => Money::parse($offer['cost']), 'selling_price' => Money::parse($offer['selling_price'])],
                );
            }
        });

        Flux::toast(text: $this->editingId ? __('Service mis à jour.') : __('Service créé.'), variant: 'success');

        $this->showModal = false;
        $this->reset(['editingId']);
    }

    /** Le service modifié est géré par l'application (frais d'expédition) : il reste interne et actif. */
    public function isEditingSystemService(): bool
    {
        return $this->editingId !== null && (bool) Service::query()->whereKey($this->editingId)->value('code');
    }

    /** @return Collection<int, Supplier> */
    public function getServiceSuppliers(): Collection
    {
        return Supplier::query()
            ->whereIn('type', [SupplierType::Service, SupplierType::Shipping])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.catalog.services', [
            'services' => Service::query()
                ->with('suppliers')
                ->when(filled($this->search), fn ($query) => $query->whereLike('name', '%'.trim($this->search).'%'))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['title' => __('Services')]);
    }
}
