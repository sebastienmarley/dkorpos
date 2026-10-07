<?php

namespace App\Livewire\CustomerOrders;

use App\Actions\CreateProductFromPriceListItem;
use App\Enums\SupplierType;
use App\Models\customer;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public bool $showCustomerModal = false;

    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    public bool $showProductModal = false;

    public string $productSupplierId = '';

    public string $productSearch = '';

    public string $priceListSearch = '';

    /** @var array<int, int> */
    public array $productIds = [];

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
            'selectedCustomer' => $this->selectedCustomerId ? customer::find($this->selectedCustomerId) : null,
        ])->layout('layouts.app', ['title' => __('Commandes clients')]);
    }
}
