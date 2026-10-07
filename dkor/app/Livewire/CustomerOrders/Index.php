<?php

namespace App\Livewire\CustomerOrders;

use App\Actions\CreateProductFromPriceListItem;
use App\Enums\SupplierType;
use App\Models\customer;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductUpc;
use App\Models\Supplier;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public bool $showCustomerModal = false;

    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    public const MAX_SALESPEOPLE = 3;

    /** @var array<int, int> */
    public const PERCENT_OPTIONS = [0, 25, 33, 50, 67, 75, 100];

    public string $upcScan = '';

    public bool $showSalespeopleModal = false;

    /** @var array<int, array{user_id: int|string, percent: int|string}> */
    public array $salespeople = [];

    /** @var array<int, array{user_id: int|string, percent: int|string}> */
    public array $salespeopleDraft = [];

    public bool $showProductModal = false;

    public string $productSupplierId = '';

    public string $productSearch = '';

    public string $priceListSearch = '';

    /** @var array<int, int> */
    public array $productIds = [];

    public function mount(): void
    {
        $this->salespeople = [['user_id' => auth()->id(), 'percent' => 100]];
    }

    public function openSalespeopleModal(): void
    {
        $this->authorize('customer_orders.assign_salespeople');

        $this->salespeopleDraft = $this->salespeople;
        $this->resetErrorBag();
        $this->showSalespeopleModal = true;
    }

    public function addSalesperson(): void
    {
        $this->authorize('customer_orders.assign_salespeople');

        if (count($this->salespeopleDraft) < self::MAX_SALESPEOPLE) {
            $this->salespeopleDraft[] = ['user_id' => '', 'percent' => 0];
        }
    }

    public function removeSalesperson(int $index): void
    {
        $this->authorize('customer_orders.assign_salespeople');

        if (count($this->salespeopleDraft) > 1) {
            unset($this->salespeopleDraft[$index]);
            $this->salespeopleDraft = array_values($this->salespeopleDraft);
        }
    }

    public function saveSalespeople(): void
    {
        $this->authorize('customer_orders.assign_salespeople');

        $rows = array_map(
            fn (array $row): array => ['user_id' => $row['user_id'], 'percent' => (int) $row['percent']],
            $this->salespeopleDraft,
        );

        if (array_sum(array_column($rows, 'percent')) === 99) {
            $rows[0]['percent']++;
        }

        $this->salespeopleDraft = $rows;

        $this->validate([
            'salespeopleDraft' => ['required', 'array', 'min:1', 'max:'.self::MAX_SALESPEOPLE],
            'salespeopleDraft.*.user_id' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
            'salespeopleDraft.*.percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        if (array_sum(array_column($rows, 'percent')) !== 100) {
            $this->addError('salespeopleDraft', __('La somme des pourcentages doit être de 100 %.'));

            return;
        }

        $this->salespeople = array_map(
            fn (array $row): array => ['user_id' => (int) $row['user_id'], 'percent' => $row['percent']],
            $rows,
        );
        $this->showSalespeopleModal = false;
    }

    /** @return Collection<int, User> */
    public function getEmployees(): Collection
    {
        return User::query()->where('is_active', true)->orderBy('lastname')->orderBy('firstname')->get();
    }

    public function openCustomerModal(): void
    {
        $this->resetCustomerSelection();
        $this->showCustomerModal = true;
    }

    public function selectCustomer(int $id): void
    {
        $customer = customer::findOrFail($id);

        $this->selectedCustomerId = $customer->id;
        $this->customerSearch = $customer->firstname.' '.$customer->lastname;
        $this->showCustomerModal = false;
    }

    public function clearCustomer(): void
    {
        $this->resetCustomerSelection();
    }

    #[On('customer-saved')]
    public function onCustomerSaved(int $id): void
    {
        $this->selectCustomer($id);
    }

    public function openProductModal(): void
    {
        $this->reset(['productSupplierId', 'productSearch', 'priceListSearch']);
        $this->showProductModal = true;
    }

    public function updatedProductSupplierId(): void
    {
        $this->reset(['productSearch', 'priceListSearch']);
    }

    public function addProduct(int $id): void
    {
        $product = Product::findOrFail($id);

        if (! in_array($product->id, $this->productIds, true)) {
            $this->productIds[] = $product->id;
        }

        $this->showProductModal = false;
    }

    public function scanUpc(): void
    {
        $upc = trim($this->upcScan);

        if ($upc === '') {
            return;
        }

        $productUpc = ProductUpc::query()->where('upc', $upc)->first();

        if ($productUpc === null) {
            $this->addError('upcScan', __('Aucun produit trouvé pour cet UPC.'));

            return;
        }

        $this->resetErrorBag('upcScan');
        $this->upcScan = '';

        if (! in_array($productUpc->product_id, $this->productIds, true)) {
            $this->productIds[] = $productUpc->product_id;
        }
    }

    public function removeProduct(int $id): void
    {
        $this->productIds = array_values(array_diff($this->productIds, [$id]));
    }

    public function addFromPriceList(int $itemId, CreateProductFromPriceListItem $createProduct): void
    {
        $this->authorize('products.create');

        $this->validate(['productSupplierId' => ['required', 'exists:suppliers,id']]);

        $item = PriceListItem::query()->inActiveLists((int) $this->productSupplierId)->findOrFail($itemId);

        $product = $createProduct->handle($item);

        if ($product->wasRecentlyCreated) {
            Flux::toast(text: __('Produit créé à partir de la liste de prix.'), variant: 'success');
        }

        $this->addProduct($product->id);
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::query()->where('type', SupplierType::Product)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Sans fournisseur, la recherche se fait uniquement par ID de produit.
     *
     * @return Collection<int, Product>
     */
    public function getProductResults(): Collection
    {
        $term = trim($this->productSearch);

        if ($term === '') {
            return new Collection;
        }

        return Product::query()
            ->with('supplier')
            ->when(
                filled($this->productSupplierId),
                fn ($query) => $query
                    ->where('supplier_id', $this->productSupplierId)
                    ->where(fn ($q) => $q
                        ->where('model', 'like', '%'.$term.'%')
                        ->orWhere('supplier_model', 'like', '%'.$term.'%')
                        ->orWhere('collection', 'like', '%'.$term.'%')
                        ->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term))),
                fn ($query) => ctype_digit($term) ? $query->whereKey((int) $term) : $query->whereRaw('0 = 1'),
            )
            ->orderBy('model')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, PriceListItem> */
    public function getPriceListResults(): Collection
    {
        $term = trim($this->priceListSearch);

        if (blank($this->productSupplierId) || mb_strlen($term) < 2) {
            return new Collection;
        }

        return PriceListItem::query()
            ->inActiveLists((int) $this->productSupplierId)
            ->where(fn ($query) => $query
                ->where('model', 'like', '%'.$term.'%')
                ->orWhere('collection', 'like', '%'.$term.'%'))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    private function resetCustomerSelection(): void
    {
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
    }

    public function render(): View
    {
        $customerResults = strlen($this->customerSearch) >= 4 && ! $this->selectedCustomerId
            ? customer::query()
                ->where(function ($q) {
                    $q->where('firstname', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('lastname', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('phone', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('cellphone', 'like', '%'.$this->customerSearch.'%');
                })
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->limit(8)
                ->get()
            : collect();

        return view('livewire.customer-orders.index', [
            'customerResults' => $customerResults,
            'products' => Product::query()->with('supplier')->whereKey($this->productIds)->get(),
            'salespeopleNames' => User::query()->whereKey(array_column($this->salespeople, 'user_id'))->get()->keyBy('id'),
            'selectedCustomer' => $this->selectedCustomerId ? customer::find($this->selectedCustomerId) : null,
        ])->layout('layouts.app', ['title' => __('Commandes clients')]);
    }
}
