<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\StoreType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ReceptionLine;
use App\Models\Store;
use App\Models\SupplierOrderLine;
use Carbon\CarbonImmutable;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Movements extends Component
{
    use WithPagination;

    /** Valeur d'un filtre qui retient tout (magasins, états ou types), à distinguer du filtre pas encore choisi. */
    public const ALL = 'all';

    /** Période des mouvements archivés, plus vieux que InventoryMovement::CURRENT_DAYS jours. */
    public const PERIOD_ARCHIVES = 'archives';

    /** Périodes courantes offertes, en jours. */
    public const PERIOD_DAYS = [7, 30, 90, 365, InventoryMovement::CURRENT_DAYS];

    public const DEFAULT_PERIOD = '90';

    #[Url(as: 'product')]
    public string $productFilter = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    /** Magasin affiché (ALL : tous), offert seulement avec stores.all (ADR 0013). */
    public string $storeFilter = '';

    /** Nombre de jours affichés, ou PERIOD_ARCHIVES. */
    public string $periodFilter = self::DEFAULT_PERIOD;

    /** Année cherchée dans les archives. */
    public string $archiveYear = '';

    public bool $showMove = false;

    public string $productSearch = '';

    public ?int $moveProductId = null;

    public string $fromStatus = 'in_stock';

    public string $toStatus = 'in_demo';

    public string $quantity = '1';

    public string $note = '';

    /** Magasin dont le stock change d'état (ADR 0012), par défaut celui de l'usager. */
    public string $storeId = '';

    /**
     * Venant de la fiche d'un produit, tout son historique courant est affiché (index product_id, created_at).
     */
    public function mount(): void
    {
        if (filled($this->productFilter)) {
            $this->periodFilter = (string) InventoryMovement::CURRENT_DAYS;
        }
    }

    public function updatedProductFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStoreFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->archiveYear = '';
        $this->resetPage();
    }

    public function updatedArchiveYear(): void
    {
        $this->resetPage();
    }

    public function openMove(): void
    {
        $this->authorize('inventory.move');

        $this->reset('productSearch', 'moveProductId', 'note');
        $this->fromStatus = InventoryStatus::InStock->value;
        $this->toStatus = InventoryStatus::InDemo->value;
        $this->quantity = '1';
        $this->resetValidation();
        $this->showMove = true;
        $this->storeId = (string) (Store::defaultIdFor(auth()->user()) ?? '');
    }

    public function selectProduct(int $productId): void
    {
        $this->authorize('inventory.move');

        $this->moveProductId = Product::findOrFail($productId)->id;
    }

    public function move(): void
    {
        $this->authorize('inventory.move');

        $movable = collect(InventoryStatus::cases())->filter->isManuallyMovable()->map->value->all();

        $this->validate([
            'storeId' => ['required', 'integer', Rule::exists('stores', 'id')->where('type', StoreType::Physical->value)->whereIn('id', Store::query()->accessibleTo(auth()->user())->pluck('id')->all())],
            'moveProductId' => ['required', 'integer', 'exists:products,id'],
            'fromStatus' => ['required', Rule::in($movable)],
            'toStatus' => ['required', Rule::in($movable), 'different:fromStatus'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            InventoryMovement::record(
                (int) $this->moveProductId,
                (int) $this->storeId,
                InventoryStatus::from($this->fromStatus),
                InventoryStatus::from($this->toStatus),
                (int) $this->quantity,
                InventoryMovementType::Transfer,
                note: $this->note,
            );
        } catch (DomainException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->showMove = false;
        $this->resetPage();

        Flux::toast(text: __('Mouvement enregistré.'), variant: 'success');
    }

    /** @return Collection<int, Product> */
    private function searchProducts(): Collection
    {
        return Product::query()
            ->when(filled($this->productSearch), fn ($query) => $query->where(function ($q) {
                $q->whereLike('model', '%'.$this->productSearch.'%')
                    ->orWhereLike('supplier_model', '%'.$this->productSearch.'%');
            }))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    /**
     * Le journal ne se charge qu'une fois un produit ou un filtre choisi.
     */
    public function hasSearchCriteria(): bool
    {
        if ($this->isSearchingArchives()) {
            return $this->archiveYearRange() !== null && $this->hasStrictCriterion();
        }

        return filled($this->productFilter) || filled($this->statusFilter) || filled($this->typeFilter) || filled($this->storeFilter);
    }

    public function isSearchingArchives(): bool
    {
        return $this->periodFilter === self::PERIOD_ARCHIVES;
    }

    /**
     * Les archives exigent un critère précis (« Tous » ne compte pas) : un produit, un magasin, un état ou un type.
     */
    private function hasStrictCriterion(): bool
    {
        return filled($this->productFilter)
            || ($this->isFilteringBy($this->storeFilter) && auth()->user()->hasAllStores())
            || $this->isFilteringBy($this->statusFilter)
            || $this->isFilteringBy($this->typeFilter);
    }

    /**
     * Bornes [début, fin[ de l'année d'archives choisie, la fin ramenée au début des mouvements courants.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function archiveYearRange(): ?array
    {
        if (! in_array((int) $this->archiveYear, $this->archiveYears(), true)) {
            return null;
        }

        $start = CarbonImmutable::create((int) $this->archiveYear);

        return [$start, $start->addYear()->min(InventoryMovement::archiveCutoff())];
    }

    /**
     * Années qui ont des mouvements archivés accessibles, de la plus récente à la plus ancienne.
     *
     * @return list<int>
     */
    private function archiveYears(): array
    {
        $cutoff = InventoryMovement::archiveCutoff();
        $oldest = InventoryMovement::query()->accessibleTo(auth()->user())->where('created_at', '<', $cutoff)->min('created_at');

        if ($oldest === null) {
            return [];
        }

        return range($cutoff->subSecond()->year, CarbonImmutable::parse($oldest)->year);
    }

    /**
     * Début de la période courante choisie (une valeur inconnue revient à la période par défaut).
     */
    private function periodStart(): CarbonImmutable
    {
        $days = in_array((int) $this->periodFilter, self::PERIOD_DAYS, true) ? (int) $this->periodFilter : (int) self::DEFAULT_PERIOD;

        return CarbonImmutable::today()->subDays($days);
    }

    /** @return array<string, string> */
    private function periodOptions(): array
    {
        $options = [];

        foreach (self::PERIOD_DAYS as $days) {
            $options[(string) $days] = match ($days) {
                365 => __('Dernière année'),
                InventoryMovement::CURRENT_DAYS => __(':days derniers jours (tout le courant)', ['days' => $days]),
                default => __(':days derniers jours', ['days' => $days]),
            };
        }

        return $options + [self::PERIOD_ARCHIVES => __('Archives (plus de :days jours)', ['days' => InventoryMovement::CURRENT_DAYS])];
    }

    private function isFilteringBy(string $filter): bool
    {
        return filled($filter) && $filter !== self::ALL;
    }

    public function render(): View
    {
        $movements = ! $this->hasSearchCriteria() ? new Paginator([], 15) : InventoryMovement::query()
            ->accessibleTo(auth()->user())
            ->with([
                'product', 'user', 'store',
                'reference' => fn (Relation $relation) => $relation instanceof MorphTo
                    ? $relation->morphWith([
                        SupplierOrderLine::class => ['order'],
                        ReceptionLine::class => ['reception'],
                    ])
                    : $relation,
            ])
            ->when(filled($this->productFilter), fn ($query) => $query->where('product_id', $this->productFilter))
            ->when($this->isFilteringBy($this->statusFilter), fn ($query) => $query->where(function ($q) {
                $q->where('from_status', $this->statusFilter)->orWhere('to_status', $this->statusFilter);
            }))
            ->when($this->isFilteringBy($this->typeFilter), fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->isFilteringBy($this->storeFilter) && auth()->user()->hasAllStores(), fn ($query) => $query->where('store_id', (int) $this->storeFilter))
            ->when(
                $this->isSearchingArchives(),
                fn ($query) => $query->where('created_at', '>=', $this->archiveYearRange()[0])->where('created_at', '<', $this->archiveYearRange()[1]),
                fn ($query) => $query->where('created_at', '>=', $this->periodStart()),
            )
            ->latest('created_at')
            ->latest('id')
            ->simplePaginate(15);

        $selectedProduct = $this->moveProductId ? Product::with('inventoryStocks')->find($this->moveProductId) : null;

        return view('livewire.inventory.movements', [
            'movements' => $movements,
            'hasSearchCriteria' => $this->hasSearchCriteria(),
            'isSearchingArchives' => $this->isSearchingArchives(),
            'periodOptions' => $this->periodOptions(),
            'archiveYears' => $this->isSearchingArchives() ? $this->archiveYears() : [],
            'statuses' => InventoryStatus::cases(),
            'movableStatuses' => collect(InventoryStatus::cases())->filter->isManuallyMovable()->values(),
            'types' => InventoryMovementType::cases(),
            'filteredProduct' => filled($this->productFilter) ? Product::find($this->productFilter) : null,
            'selectedProduct' => $selectedProduct,
            'stores' => Store::query()->accessibleTo(auth()->user())->where('type', StoreType::Physical)->orderBy('name')->get(),
            'productResults' => $this->showMove && ! $this->moveProductId ? $this->searchProducts() : collect(),
        ])->layout('layouts.app', ['title' => __('Journal d\'inventaire')]);
    }
}
