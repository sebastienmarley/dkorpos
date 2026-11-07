<?php

namespace App\Livewire\Products;

use App\Enums\StoreType;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\ProductDemo;
use App\Models\Store;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Component;

/**
 * Démonstrateurs et liquidation du produit, par magasin de stock (ADR 0018).
 */
class DemosAndClearances extends Component
{
    public Product $product;

    public ?int $demoStoreId = null;

    public string $demoPrice = '';

    public string $demoNote = '';

    public bool $demoIsAsIs = false;

    /** @var array<int, string> prix de liquidation saisi, par magasin */
    public array $clearancePrices = [];

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->clearancePrices = $product->clearances()->active()->pluck('price', 'store_id')
            ->map(fn (Money $price): string => $price->toDecimal())
            ->all();
    }

    public function openDemoForm(int $storeId): void
    {
        $this->authorizeStore($storeId);

        $this->reset(['demoPrice', 'demoNote', 'demoIsAsIs']);
        $this->demoStoreId = $storeId;
        $this->resetValidation();
    }

    public function declareDemo(): void
    {
        $this->authorizeStore((int) $this->demoStoreId);

        $this->demoPrice = str_replace(',', '.', trim($this->demoPrice));
        $this->validate([
            'demoPrice' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'demoNote' => ['nullable', 'string', 'max:255'],
            'demoIsAsIs' => ['boolean'],
        ]);

        try {
            $this->product->declareDemo((int) $this->demoStoreId, Money::parse($this->demoPrice), trim($this->demoNote), $this->demoIsAsIs);
        } catch (DomainException $exception) {
            $this->addError('demoPrice', $exception->getMessage());

            return;
        }

        $this->demoStoreId = null;
        Flux::toast(text: __('Démonstrateur ajouté.'), variant: 'success');
    }

    public function removeDemo(int $demoId): void
    {
        $demo = $this->product->demos()->findOrFail($demoId);
        $this->authorizeStore($demo->store_id);

        if ($demo->isTaken()) {
            Flux::toast(text: __('Ce démonstrateur est vendu ou réservé à une commande : il ne se retire pas.'), variant: 'danger');

            return;
        }

        $demo->delete();
        Flux::toast(text: __('Démonstrateur retiré.'), variant: 'success');
    }

    public function saveClearance(int $storeId): void
    {
        $this->authorizeStore($storeId);

        $this->clearancePrices[$storeId] = str_replace(',', '.', trim($this->clearancePrices[$storeId] ?? ''));
        $this->validate(["clearancePrices.{$storeId}" => ['required', 'numeric', 'min:0', 'max:99999999.99']], attributes: ["clearancePrices.{$storeId}" => __('prix de liquidation')]);

        $this->product->clearAt($storeId, Money::parse($this->clearancePrices[$storeId]));
        Flux::toast(text: __('Prix de liquidation enregistré.'), variant: 'success');
    }

    public function endClearance(int $storeId): void
    {
        $this->authorizeStore($storeId);

        $this->product->endClearanceAt($storeId);
        unset($this->clearancePrices[$storeId]);
        Flux::toast(text: __('Liquidation terminée.'), variant: 'success');
    }

    /** L'usager gère les démonstrateurs et liquidations de ce magasin (ADR 0013). */
    public function canManage(int $storeId): bool
    {
        return (auth()->user()?->can('clearances.manage') ?? false) && auth()->user()->canActForStore($storeId);
    }

    private function authorizeStore(int $storeId): void
    {
        $this->authorize('clearances.manage');
        abort_unless(auth()->user()->canActForStore($storeId) && $this->stockStores()->contains('id', $storeId), 403);
    }

    /** @return Collection<int, Store> Magasins qui tiennent du stock (ADR 0012) : actifs, physiques, sans entrepôt. */
    private function stockStores(): Collection
    {
        return Store::query()->where('type', StoreType::Physical)->where('is_active', true)->whereNull('warehouse_store_id')->orderBy('name')->get();
    }

    /**
     * @return SupportCollection<int, array{store: Store, inDemo: int, demos: Collection<int, ProductDemo>, clearance: ?ProductClearance}>
     */
    public function getStoreRows(): SupportCollection
    {
        $stocks = InventoryStock::query()->where('product_id', $this->product->id)->pluck('quantity_in_demo', 'store_id');
        $demos = $this->product->demos()->with(['lines' => fn ($query) => $query->with('order')->orderBy('id')])->orderBy('id')->get()->groupBy('store_id');
        $clearances = $this->product->clearances()->active()->get()->keyBy('store_id');

        return $this->stockStores()
            ->map(fn (Store $store): array => [
                'store' => $store,
                'inDemo' => (int) ($stocks[$store->id] ?? 0),
                'demos' => $demos->get($store->id, new Collection),
                'clearance' => $clearances->get($store->id),
            ])
            ->filter(fn (array $row): bool => $row['inDemo'] > 0 || $row['demos']->isNotEmpty() || $row['clearance'] !== null || $this->canManage($row['store']->id))
            ->values();
    }

    public function render(): View
    {
        return view('livewire.products.demos-and-clearances');
    }
}
