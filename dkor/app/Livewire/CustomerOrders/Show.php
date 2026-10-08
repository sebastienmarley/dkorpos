<?php

namespace App\Livewire\CustomerOrders;

use App\Actions\CreateProductFromPriceListItem;
use App\Concerns\SearchesCustomers;
use App\Enums\SupplierType;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductUpc;
use App\Models\Supplier;
use App\Models\User;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use SearchesCustomers;

    public const MAX_SALESPEOPLE = 3;

    /** @var array<int, int> */
    public const PERCENT_OPTIONS = [0, 25, 33, 50, 67, 75, 100];

    public CustomerOrder $order;

    public bool $showCustomerModal = false;

    public string $upcScan = '';

    public bool $showSalespeopleModal = false;

    /** @var array<int, array{user_id: int|string, percent: int|string}> */
    public array $salespeopleDraft = [];

    public bool $showProductModal = false;

    public string $productSupplierId = '';

    public string $productSearch = '';

    public string $priceListSearch = '';

    /** @var array<int, array{reserved: int|string, on_order: int|string}> */
    public array $lineQuantities = [];

    public function mount(CustomerOrder $order): void
    {
        $this->order = $order;
        $this->refreshLineQuantities();
    }

    public function openSalespeopleModal(): void
    {
        $this->authorize('customer_orders.assign_salespeople');

        $this->salespeopleDraft = $this->order->salespeopleShares();

        if ($this->salespeopleDraft === []) {
            $this->salespeopleDraft = [['user_id' => '', 'percent' => 100]];
        }

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

        $rows = array_values(array_map(
            fn (array $row): array => ['user_id' => $row['user_id'], 'percent' => (int) $row['percent']],
            $this->salespeopleDraft,
        ));

        if (array_sum(array_column($rows, 'percent')) === 99) {
            $rows = array_map(
                fn (array $row, int $index): array => ['user_id' => $row['user_id'], 'percent' => $row['percent'] + ($index === 0 ? 1 : 0)],
                $rows,
                array_keys($rows),
            );
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

        $this->order->syncSalespeople(array_map(
            fn (array $row): array => ['user_id' => (int) $row['user_id'], 'percent' => $row['percent']],
            $rows,
        ));

        $this->showSalespeopleModal = false;
    }

    /** @return Collection<int, User> */
    public function getEmployees(): Collection
    {
        return User::query()->where('is_active', true)->orderBy('lastname')->orderBy('firstname')->get();
    }

    public function openCustomerModal(): void
    {
        $this->authorize('customer_orders.edit');

        $this->customerSearch = '';
        $this->showCustomerModal = true;
    }

    public function selectCustomer(int $id): void
    {
        $this->authorize('customer_orders.edit');

        $customer = customer::findOrFail($id);

        $this->order->update(['customer_id' => $customer->id]);
        $this->showCustomerModal = false;
    }

    #[On('customer-saved')]
    public function onCustomerSaved(int $id): void
    {
        if ($this->showCustomerModal) {
            $this->selectCustomer($id);
        }
    }

    public function openProductModal(): void
    {
        $this->authorize('customer_orders.edit');

        $this->reset(['productSupplierId', 'productSearch', 'priceListSearch']);
        $this->showProductModal = true;
    }

    public function updatedProductSupplierId(): void
    {
        $this->reset(['productSearch', 'priceListSearch']);
    }

    public function addProduct(int $id): void
    {
        $this->authorize('customer_orders.edit');

        $this->order->addProduct(Product::findOrFail($id));

        $this->showProductModal = false;
        $this->refreshLineQuantities();
    }

    public function scanUpc(): void
    {
        $this->authorize('customer_orders.edit');

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

        $this->addProduct($productUpc->product_id);
    }

    public function removeLine(int $lineId): void
    {
        $this->authorize('customer_orders.edit');

        try {
            $this->order->removeLine($this->order->lines()->findOrFail($lineId));
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'warning');
        }

        $this->refreshLineQuantities();
    }

    /**
     * Applique la répartition stock réservé / en commande saisie pour une ligne (clé « {ligne}.reserved »
     * ou « {ligne}.on_order »). En cas de refus (stock plus disponible), les quantités réelles sont réaffichées.
     */
    public function updatedLineQuantities(mixed $value, string $key): void
    {
        $this->authorize('customer_orders.edit');

        $lineId = (int) explode('.', $key)[0];
        $line = $this->order->lines()->findOrFail($lineId);

        $this->resetErrorBag('lineQuantities.'.$lineId);

        $this->validate([
            'lineQuantities.'.$lineId.'.reserved' => ['required', 'integer', 'min:0'],
            'lineQuantities.'.$lineId.'.on_order' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $this->order->adjustLine(
                $line,
                (int) $this->lineQuantities[$lineId]['reserved'],
                (int) $this->lineQuantities[$lineId]['on_order'],
            );
        } catch (DomainException $exception) {
            $this->addError('lineQuantities.'.$lineId, $exception->getMessage());
        }

        $this->refreshLineQuantities();
    }

    private function refreshLineQuantities(): void
    {
        $this->lineQuantities = $this->order->lines()->get()
            ->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => [
                'reserved' => $line->quantity_reserved,
                'on_order' => $line->quantity_on_order,
            ]])
            ->all();
    }

    public function addFromPriceList(int $itemId, CreateProductFromPriceListItem $createProduct): void
    {
        $this->authorize('customer_orders.edit');
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

    public function render(): View
    {
        $this->order->load(['customer', 'creator', 'salespeople', 'lines.product.supplier', 'lines.product.inventoryStock']);

        return view('livewire.customer-orders.show', [
            'customerResults' => $this->getCustomerResults(),
        ])->layout('layouts.app', ['title' => __('Commande client #:id', ['id' => $this->order->id])]);
    }
}
