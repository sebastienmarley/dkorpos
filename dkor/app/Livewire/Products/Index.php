<?php

namespace App\Livewire\Products;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public const AVAILABILITY_IN_STOCK = 'in_stock';

    public const AVAILABILITY_IN_DEMO = 'in_demo';

    public const AVAILABILITY_CLEARANCE = 'clearance';

    public const AVAILABILITY_ON_ORDER = 'on_order';

    public string $search = '';

    public string $supplierId = '';

    /** Disponibilité dans les magasins de l'usager : une constante AVAILABILITY_*, vide pour tout produit. */
    public string $availabilityFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatedAvailabilityFilter(): void
    {
        $this->resetPage();
    }

    /**
     * La liste ne se charge qu'une fois une recherche ou un filtre choisi.
     */
    public function hasSearchCriteria(): bool
    {
        return filled(trim($this->search)) || filled($this->supplierId) || array_key_exists($this->availabilityFilter, $this->availabilityOptions());
    }

    /**
     * Recherche par ID (un nombre), modèle ou modèle fournisseur ; le fournisseur a son propre filtre. Chaque produit
     * porte son stock dans les magasins de l'usager (stock_in_stock, stock_in_demo, stock_on_order, stock_reserved,
     * stock_customer_order, on_clearance).
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getProducts(): LengthAwarePaginator
    {
        if (! $this->hasSearchCriteria()) {
            return new LengthAwarePaginator([], 0, 10);
        }

        $term = trim($this->search);
        $inStockStores = fn (Builder $query) => $this->limitToStockStores($query);

        return Product::with('supplier')
            ->withSum(['inventoryStocks as stock_in_stock' => $inStockStores], 'quantity_in_stock')
            ->withSum(['inventoryStocks as stock_in_demo' => $inStockStores], 'quantity_in_demo')
            ->withSum(['inventoryStocks as stock_on_order' => $inStockStores], 'quantity_on_order')
            ->withSum(['inventoryStocks as stock_reserved' => $inStockStores], 'quantity_reserved')
            ->withSum(['inventoryStocks as stock_customer_order' => $inStockStores], 'quantity_customer_order')
            ->withExists(['clearances as on_clearance' => fn (Builder $query) => $this->limitToActiveClearances($query)])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->whereLike('model', '%'.$term.'%')
                        ->orWhereLike('supplier_model', '%'.$term.'%')
                        ->when(ctype_digit($term), fn ($byId) => $byId->orWhere('id', (int) $term));
                });
            })
            ->when(filled($this->supplierId), fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->when($this->availabilityFilter === self::AVAILABILITY_CLEARANCE, fn ($q) => $q->whereHas('clearances', fn (Builder $query) => $this->limitToActiveClearances($query)))
            ->when(
                in_array($this->availabilityFilter, [self::AVAILABILITY_IN_STOCK, self::AVAILABILITY_IN_DEMO, self::AVAILABILITY_ON_ORDER], true),
                fn ($q) => $q->whereHas('inventoryStocks', fn (Builder $query) => $this->limitToStockStores($query)->where('quantity_'.$this->availabilityFilter, '>', 0)),
            )
            ->orderBy('model')
            ->paginate(10);
    }

    /**
     * Magasins dont le stock compte pour l'usager (ADR 0012, 0013) : tous avec stores.all, sinon celui qui sert ses
     * ventes (son entrepôt s'il en a un). Null : aucune limite.
     *
     * @return list<int>|null
     */
    private function stockStoreIds(): ?array
    {
        $user = auth()->user();

        if ($user->hasAllStores()) {
            return null;
        }

        $store = $user->store_id ? Store::find($user->store_id) : null;

        return $store ? [$store->stockStoreId()] : [];
    }

    /**
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function limitToStockStores(Builder $query): Builder
    {
        $storeIds = $this->stockStoreIds();

        return $storeIds === null ? $query : $query->whereIn($query->qualifyColumn('store_id'), $storeIds);
    }

    /**
     * Liquidations en cours dans les magasins de l'usager, seulement là où il reste du stock à liquider.
     *
     * @param  Builder<ProductClearance>  $query
     * @return Builder<ProductClearance>
     */
    private function limitToActiveClearances(Builder $query): Builder
    {
        $clearances = $query->getModel()->getTable();
        $stocks = (new InventoryStock)->getTable();

        return $this->limitToStockStores($query->active())
            ->whereExists(fn (QueryBuilder $stock) => $stock->from($stocks)
                ->whereColumn($stocks.'.product_id', $clearances.'.product_id')
                ->whereColumn($stocks.'.store_id', $clearances.'.store_id')
                ->where($stocks.'.quantity_in_stock', '>', 0));
    }

    /** @return array<string, string> */
    public function availabilityOptions(): array
    {
        return [
            self::AVAILABILITY_IN_STOCK => __('En stock'),
            self::AVAILABILITY_IN_DEMO => __('En démo'),
            self::AVAILABILITY_CLEARANCE => __('En liquidation'),
            self::AVAILABILITY_ON_ORDER => __('En commande'),
        ];
    }

    /**
     * Fournisseurs qui ont au moins un produit.
     *
     * @return Collection<int, Supplier>
     */
    public function getSuppliers(): Collection
    {
        return Supplier::query()->whereHas('products')->orderBy('name')->get();
    }

    #[On('product-saved')]
    public function refreshProducts(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.products.index', [
            'products' => $this->getProducts(),
            'suppliers' => $this->getSuppliers(),
            'availabilityOptions' => $this->availabilityOptions(),
            'hasSearchCriteria' => $this->hasSearchCriteria(),
        ])->layout('layouts.app', ['title' => __('Produits')]);
    }
}
