<?php

namespace App\Livewire\CustomerOrders;

use App\Actions\CreateProductFromPriceListItem;
use App\Concerns\SearchesCustomers;
use App\Enums\SupplierType;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
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

    public bool $showLineModal = false;

    public ?int $editingLineId = null;

    public string $editReserved = '';

    public string $editOnOrder = '';

    public string $editUnitPrice = '';

    public string $editNote = '';

    public bool $showReturnModal = false;

    public ?int $returnLineId = null;

    public string $returnStep = 'choice';

    public string $returnQuantity = '1';

    public string $returnType = 'exchange';

    public float $returnRefundable = 0;

    /** @var array<int, array{method_id: int|string, amount: string}> */
    public array $returnRefunds = [];

    public bool $showCreditRefundModal = false;

    /** @var array<int, array{method_id: int|string, amount: string}> */
    public array $creditRefunds = [];

    public bool $showPickupModal = false;

    public string $pickupStep = 'items';

    /** @var array<int, int|string> quantité à ramasser par ligne */
    public array $pickupQuantities = [];

    public float $pickupRequired = 0;

    /** Montant payé avec le crédit au compte du client (champ affiché seulement si le client a un crédit). */
    public string $pickupCredit = '0.00';

    /** @var array<int, array{method_id: int|string, amount: string, tendered?: string}> */
    public array $pickupPayments = [];

    public function mount(CustomerOrder $order): void
    {
        $this->order = $order;
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

        try {
            $this->order->addProduct(Product::findOrFail($id));
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        $this->showProductModal = false;
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
    }

    public function openLineModal(int $lineId): void
    {
        $this->authorize('customer_orders.edit');

        $line = $this->order->lines()->findOrFail($lineId);

        $this->editingLineId = $line->id;
        $this->editReserved = (string) $line->quantity_reserved;
        $this->editOnOrder = (string) $line->quantity_on_order;
        $this->editUnitPrice = number_format($line->unit_price, 2, '.', '');
        $this->editNote = $line->note ?? '';
        $this->resetErrorBag();
        $this->showLineModal = true;
    }

    public function saveLine(): void
    {
        $this->authorize('customer_orders.edit');

        $line = $this->order->lines()->findOrFail($this->editingLineId);

        $this->editUnitPrice = str_replace(',', '.', $this->editUnitPrice);

        $this->validate([
            'editReserved' => ['required', 'integer', 'min:0'],
            'editOnOrder' => ['required', 'integer', 'min:0'],
            'editUnitPrice' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'editNote' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $this->order->updateLine($line, (int) $this->editReserved, (int) $this->editOnOrder, (float) $this->editUnitPrice, $this->editNote);
        } catch (DomainException $exception) {
            $this->addError('editReserved', $exception->getMessage());

            return;
        }

        $this->showLineModal = false;
        $this->reset(['editingLineId', 'editReserved', 'editOnOrder', 'editUnitPrice', 'editNote']);
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

    /**
     * Ouvre le retour d'une ligne remise au client (depuis le modal d'édition de la ligne).
     */
    public function openReturnModal(int $lineId): void
    {
        $this->authorize('customer_orders.edit');

        $line = $this->order->lines()->findOrFail($lineId);

        if (! $line->status->isHandedOver()) {
            Flux::toast(text: __('Seul un article livré, ramassé ou expédié peut être retourné.'), variant: 'warning');

            return;
        }

        $this->returnLineId = $line->id;
        $this->returnQuantity = (string) $line->quantity;
        $this->returnType = 'exchange';
        $this->returnStep = 'choice';
        $this->returnRefunds = [];
        $this->resetErrorBag();
        $this->showLineModal = false;
        $this->showReturnModal = true;
    }

    /**
     * Échange : reprend l'article et revient à la commande pour ajouter le produit d'échange. Remboursement : passe
     * à l'étape du remboursement.
     */
    public function continueReturn(): void
    {
        $this->authorize('customer_orders.edit');

        $line = $this->order->lines()->findOrFail($this->returnLineId);

        $this->validate([
            'returnQuantity' => ['required', 'integer', 'min:1', 'max:'.$line->quantity],
            'returnType' => ['required', Rule::in(['exchange', 'refund'])],
        ]);

        if ($this->returnType === 'refund') {
            $this->returnRefundable = $this->order->refundableFor($line, (int) $this->returnQuantity);
            $firstMethod = CustomerPaymentMethod::query()->where('is_active', true)->orderBy('name')->value('id');
            $this->returnRefunds = [['method_id' => $firstMethod ?? '', 'amount' => number_format($this->returnRefundable, 2, '.', '')]];
            $this->returnStep = 'refund';

            return;
        }

        try {
            $this->order->returnLine($line, (int) $this->returnQuantity, refund: false);
        } catch (DomainException $exception) {
            $this->addError('returnQuantity', $exception->getMessage());

            return;
        }

        $this->closeReturnModal();
        Flux::toast(text: __('Article repris. Ajoutez le produit d\'échange : le montant payé reste au crédit de la commande.'), variant: 'success');
    }

    public function backToReturnChoice(): void
    {
        $this->returnStep = 'choice';
        $this->resetErrorBag();
    }

    public function addReturnRefund(): void
    {
        $this->returnRefunds[] = ['method_id' => '', 'amount' => '0.00'];
    }

    public function removeReturnRefund(int $index): void
    {
        unset($this->returnRefunds[$index]);
        $this->returnRefunds = array_values($this->returnRefunds);
    }

    public function confirmReturnRefund(): void
    {
        $this->authorize('customer_orders.edit');

        $line = $this->order->lines()->findOrFail($this->returnLineId);

        $this->returnRefunds = array_map(
            fn (array $refund): array => ['method_id' => $refund['method_id'], 'amount' => str_replace(',', '.', (string) $refund['amount'])],
            $this->returnRefunds,
        );

        $this->validate([
            'returnRefunds.*.method_id' => ['required', 'integer', Rule::exists('customer_payment_methods', 'id')->where('is_active', true)],
            'returnRefunds.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        try {
            $this->order->returnLine(
                $line,
                (int) $this->returnQuantity,
                refund: true,
                refunds: array_map(fn (array $refund): array => ['method_id' => (int) $refund['method_id'], 'amount' => (float) $refund['amount']], $this->returnRefunds),
            );
        } catch (DomainException $exception) {
            $this->addError('returnRefunds', $exception->getMessage());

            return;
        }

        $this->closeReturnModal();
        Flux::toast(text: __('Retour remboursé.'), variant: 'success');
    }

    public function returnRefundsTotal(): float
    {
        return round(array_sum(array_map(fn (array $refund): float => (float) str_replace(',', '.', (string) $refund['amount']), $this->returnRefunds)), 2);
    }

    private function closeReturnModal(): void
    {
        $this->showReturnModal = false;
        $this->reset(['returnLineId', 'returnQuantity', 'returnType', 'returnStep', 'returnRefundable', 'returnRefunds']);
    }

    public function openCreditRefundModal(): void
    {
        $this->authorize('customer_orders.edit');

        $firstMethod = CustomerPaymentMethod::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->creditRefunds = [['method_id' => $firstMethod ?? '', 'amount' => number_format($this->order->credit(), 2, '.', '')]];
        $this->resetErrorBag();
        $this->showCreditRefundModal = true;
    }

    public function addCreditRefund(): void
    {
        $this->creditRefunds[] = ['method_id' => '', 'amount' => '0.00'];
    }

    public function removeCreditRefund(int $index): void
    {
        unset($this->creditRefunds[$index]);
        $this->creditRefunds = array_values($this->creditRefunds);
    }

    public function refundCredit(): void
    {
        $this->authorize('customer_orders.edit');

        $this->creditRefunds = array_map(
            fn (array $refund): array => ['method_id' => $refund['method_id'], 'amount' => str_replace(',', '.', (string) $refund['amount'])],
            $this->creditRefunds,
        );

        $this->validate([
            'creditRefunds.*.method_id' => ['required', 'integer', Rule::exists('customer_payment_methods', 'id')->where('is_active', true)],
            'creditRefunds.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        try {
            $this->order->refundCredit(array_map(fn (array $refund): array => ['method_id' => (int) $refund['method_id'], 'amount' => (float) $refund['amount']], $this->creditRefunds));
        } catch (DomainException $exception) {
            $this->addError('creditRefunds', $exception->getMessage());

            return;
        }

        $this->showCreditRefundModal = false;
        $this->reset('creditRefunds');
        Flux::toast(text: __('Crédit remboursé.'), variant: 'success');
    }

    public function creditRefundsTotal(): float
    {
        return round(array_sum(array_map(fn (array $refund): float => (float) str_replace(',', '.', (string) $refund['amount']), $this->creditRefunds)), 2);
    }

    public function transferCreditToCustomer(): void
    {
        $this->authorize('customer_orders.edit');

        try {
            $credit = $this->order->transferCreditToCustomer();
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(text: __(':amount $ portés au compte du client.', ['amount' => number_format($credit, 2)]), variant: 'success');
    }

    /**
     * Le client annule une ligne commandée sans attendre la réponse du fournisseur : frais d'annulation du magasin.
     */
    public function cancelLineWithFee(int $lineId): void
    {
        $this->authorize('customer_orders.edit');

        try {
            $fee = $this->order->cancelLineWithFee($this->order->lines()->findOrFail($lineId));
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        $this->showLineModal = false;
        $this->order->refresh();
        Flux::toast(text: __('Ligne annulée avec :fee $ de frais. Le crédit éventuel peut être remboursé ou porté au compte du client.', ['fee' => number_format($fee, 2)]), variant: 'success');
    }

    #[On('supplier-line-cancellation-requested')]
    public function onSupplierLineCancellationRequested(): void
    {
        $this->order->refresh();
    }

    public function openPickupModal(): void
    {
        $this->authorize('customer_orders.edit');

        $this->pickupQuantities = $this->order->lines()->get()
            ->filter(fn ($line): bool => $line->status->isPickable() && $line->quantity_reserved > 0)
            ->mapWithKeys(fn ($line): array => [$line->id => $line->quantity_reserved])
            ->all();
        $this->pickupStep = 'items';
        $this->pickupPayments = [];
        $this->resetErrorBag();
        $this->showPickupModal = true;
    }

    /**
     * Valide les quantités choisies et calcule le montant à encaisser : 100 % des articles remis, dépôt sur le reste.
     */
    public function continueToPayment(): void
    {
        $this->authorize('customer_orders.edit');

        $this->validate([
            'pickupQuantities' => ['present', 'array'],
            'pickupQuantities.*' => ['required', 'integer', 'min:0'],
        ]);

        $quantities = $this->pickupQuantitiesAsIntegers();

        $available = $this->order->lines()->whereKey(array_keys($quantities))->pluck('quantity_reserved', 'id');

        foreach ($quantities as $lineId => $quantity) {
            if ($quantity > (int) ($available[$lineId] ?? 0)) {
                $this->addError('pickupQuantities.'.$lineId, __('Maximum : :count', ['count' => (int) ($available[$lineId] ?? 0)]));

                return;
            }
        }

        $this->pickupRequired = $this->order->amountRequiredFor($quantities);
        $credit = min($this->order->customer()->value('credit_balance') ?? 0, $this->pickupRequired);
        $this->pickupCredit = number_format((float) $credit, 2, '.', '');

        if (array_sum($quantities) === 0 && $this->order->balance_due <= 0) {
            $this->addError('pickupQuantities', __('Aucun article choisi et rien à payer.'));

            return;
        }

        $firstMethod = CustomerPaymentMethod::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->pickupPayments = [['method_id' => $firstMethod ?? '', 'amount' => number_format(max(0, $this->pickupRequired - (float) $credit), 2, '.', '')]];
        $this->pickupStep = 'payment';
    }

    public function backToPickupItems(): void
    {
        $this->pickupStep = 'items';
        $this->resetErrorBag();
    }

    public function addPickupPayment(): void
    {
        $remaining = max(0, round($this->pickupRequired - $this->pickupPaymentsTotal(), 2));

        $this->pickupPayments[] = ['method_id' => '', 'amount' => number_format($remaining, 2, '.', '')];
    }

    public function removePickupPayment(int $index): void
    {
        unset($this->pickupPayments[$index]);
        $this->pickupPayments = array_values($this->pickupPayments);
    }

    public function confirmPickup(): void
    {
        $this->authorize('customer_orders.edit');

        $this->pickupPayments = array_map(
            fn (array $payment): array => [
                'method_id' => $payment['method_id'],
                'amount' => str_replace(',', '.', (string) $payment['amount']),
                'tendered' => str_replace(',', '.', (string) ($payment['tendered'] ?? '')),
            ],
            $this->pickupPayments,
        );

        $this->pickupCredit = str_replace(',', '.', $this->pickupCredit);
        $this->pickupPayments = array_values(array_filter(
            $this->pickupPayments,
            fn (array $payment): bool => (float) $payment['amount'] > 0 || filled($payment['method_id']),
        ));

        $this->validate([
            'pickupCredit' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'pickupPayments.*.method_id' => ['required', 'integer', Rule::exists('customer_payment_methods', 'id')->where('is_active', true)],
            'pickupPayments.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'pickupPayments.*.tendered' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        try {
            $pickup = $this->order->pickUp(
                $this->pickupQuantitiesAsIntegers(),
                array_map(fn (array $payment): array => [
                    'method_id' => (int) $payment['method_id'],
                    'amount' => (float) $payment['amount'],
                    'tendered' => filled($payment['tendered']) ? (float) $payment['tendered'] : null,
                ], $this->pickupPayments),
                (float) $this->pickupCredit,
            );
        } catch (DomainException $exception) {
            $this->addError('pickupPayments', $exception->getMessage());

            return;
        }

        $this->showPickupModal = false;
        $this->reset(['pickupQuantities', 'pickupPayments', 'pickupRequired', 'pickupCredit']);
        Flux::toast(text: $pickup === null ? __('Paiement enregistré.') : __('Ramassage enregistré.'), variant: 'success');
    }

    /** Total reçu : modes de paiement et crédit client utilisé. */
    public function pickupPaymentsTotal(): float
    {
        return round(array_sum(array_map(fn (array $payment): float => (float) str_replace(',', '.', (string) $payment['amount']), $this->pickupPayments))
            + (float) str_replace(',', '.', $this->pickupCredit), 2);
    }

    /** @return Collection<int, CustomerPaymentMethod> */
    public function getPaymentMethods(): Collection
    {
        return CustomerPaymentMethod::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function cashMethodId(): int
    {
        return CustomerPaymentMethod::cash()->id;
    }

    /**
     * Montant à percevoir ou à remettre en comptant (arrondi au 5 ¢ près).
     */
    public function roundedCash(int|string $amount): float
    {
        return CustomerPaymentMethod::roundCash((float) str_replace(',', '.', (string) $amount));
    }

    /**
     * Monnaie à rendre pour une ligne de paiement comptant du ramassage.
     */
    public function changeDue(int $index): float
    {
        $payment = $this->pickupPayments[$index] ?? null;

        if ($payment === null || blank($payment['tendered'] ?? null)) {
            return 0.0;
        }

        return max(0.0, round((float) str_replace(',', '.', (string) $payment['tendered']) - $this->roundedCash($payment['amount']), 2));
    }

    /** @return array<int, int> */
    private function pickupQuantitiesAsIntegers(): array
    {
        return array_map(fn (int|string $quantity): int => (int) $quantity, $this->pickupQuantities);
    }

    public function render(): View
    {
        $editingLine = $this->editingLineId ? $this->order->lines()->with(['product.inventoryStock', 'supplierOrderLine.order'])->find($this->editingLineId) : null;
        $returnLine = $this->returnLineId ? $this->order->lines()->with('product')->find($this->returnLineId) : null;

        $this->order->load(['customer', 'store', 'creator', 'salespeople', 'lines.product.supplier', 'lines.product.inventoryStock', 'payments.paymentMethod', 'payments.receiver']);

        return view('livewire.customer-orders.show', [
            'customerResults' => $this->getCustomerResults(),
            'editingLine' => $editingLine,
            'returnLine' => $returnLine,
        ])->layout('layouts.app', ['title' => __('Commande client #:id', ['id' => $this->order->id])]);
    }
}
