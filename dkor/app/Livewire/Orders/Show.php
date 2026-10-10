<?php

namespace App\Livewire\Orders;

use App\Enums\SupplierType;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    public SupplierOrder $order;

    public string $quoteNumber = '';

    public string $notes = '';

    // Transport
    public bool $isCollect = false;

    public string $shippingSupplierId = '';

    // Drop ship
    public bool $isDropShip = false;

    public string $dropShipName = '';

    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $dropShipAddress = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    // Nouvelle ligne
    public string $productId = '';

    public string $productSearch = '';

    public string $description = '';

    public string $quantity = '1';

    public string $unitCost = '';

    // Édition d'une ligne
    public ?int $editingLineId = null;

    public string $editQuantity = '';

    public string $editUnitCost = '';

    // Substitution d'un produit
    public bool $showSubstitute = false;

    public ?int $substituteLineId = null;

    public string $substituteSearch = '';

    public ?int $substituteProductId = null;

    // Renversement d'une réception
    public bool $showReverse = false;

    public ?int $reverseReceptionLineId = null;

    public string $reverseQuantity = '';

    public string $reverseReason = '';

    // Réception
    public bool $showReceive = false;

    /** @var array<int, array{quantity: string}> */
    public array $receipts = [];

    public function mount(SupplierOrder $order): void
    {
        $this->order = $order;
        $this->quoteNumber = $order->quote_number ?? '';
        $this->notes = $order->notes ?? '';
        $this->isCollect = $order->isCollectShipping();
        $this->shippingSupplierId = (string) ($order->shipping_supplier_id ?? '');
        $this->isDropShip = $order->is_drop_ship;
        $this->dropShipName = $order->drop_ship_name ?? '';
        $this->dropShipAddress = [
            'civic' => $order->drop_ship_address_civic ?? '',
            'apartment' => $order->drop_ship_address_apartment ?? '',
            'street' => $order->drop_ship_address_street ?? '',
            'city' => $order->drop_ship_address_city ?? '',
            'province' => $order->drop_ship_address_province ?? '',
            'country' => $order->drop_ship_address_country ?? 'CA',
            'postal_code' => $order->drop_ship_address_postal_code ?? '',
        ];
    }

    public function updatedDropShipAddressCountry(): void
    {
        $this->dropShipAddress['province'] = '';
    }

    public function saveDropShip(): void
    {
        $this->authorize('supplier_orders.edit');

        $required = Rule::requiredIf($this->isDropShip);

        $this->validate([
            'isDropShip' => ['boolean'],
            'dropShipName' => [$required, 'nullable', 'string', 'max:255'],
            'dropShipAddress.civic' => [$required, 'nullable', 'string', 'max:20'],
            'dropShipAddress.apartment' => ['nullable', 'string', 'max:20'],
            'dropShipAddress.street' => [$required, 'nullable', 'string', 'max:255'],
            'dropShipAddress.city' => [$required, 'nullable', 'string', 'max:100'],
            'dropShipAddress.province' => [$required, 'nullable', 'string', 'size:2'],
            'dropShipAddress.country' => [$required, 'nullable', 'string', 'in:CA,US'],
            'dropShipAddress.postal_code' => [$required, 'nullable', 'string', 'max:6'],
        ]);

        $this->runTransition(
            fn () => $this->order->setDropShip($this->isDropShip, ['name' => $this->dropShipName, ...$this->dropShipAddress]),
            __('Livraison sauvegardée.'),
        );

        $this->isDropShip = $this->order->is_drop_ship;
    }

    public function saveShipping(): void
    {
        $this->authorize('supplier_orders.edit');

        $this->validate([
            'isCollect' => ['boolean'],
            'shippingSupplierId' => [
                Rule::requiredIf($this->isCollect || $this->order->supplier->collect),
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('type', SupplierType::Shipping->value)->where('is_active', 1),
            ],
        ]);

        $this->runTransition(
            fn () => $this->order->setShipping($this->isCollect, filled($this->shippingSupplierId) ? (int) $this->shippingSupplierId : null),
            __('Transport sauvegardé.'),
        );

        $this->isCollect = $this->order->isCollectShipping();
        $this->shippingSupplierId = (string) ($this->order->shipping_supplier_id ?? '');
    }

    public function updatedProductId(): void
    {
        $this->fillCostFromProduct();
    }

    public function selectProduct(int $productId): void
    {
        $this->authorize('supplier_orders.edit');

        $this->productId = (string) $this->orderableProducts()->findOrFail($productId)->id;
        $this->productSearch = '';
        $this->fillCostFromProduct();

        $this->js("document.querySelector('[data-line-quantity] input')?.select()");
    }

    /**
     * Touche Entrée dans la recherche: choisit le premier résultat de ce qui a été saisi.
     */
    public function selectFirstProduct(string $term): void
    {
        $this->authorize('supplier_orders.edit');

        $first = $this->searchOrderableProducts($term)->first();

        if ($first !== null) {
            $this->selectProduct($first->id);
        }
    }

    public function clearProduct(): void
    {
        $this->reset('productId', 'productSearch', 'unitCost');

        $this->js("document.querySelector('[data-line-search] input')?.focus()");
    }

    private function fillCostFromProduct(): void
    {
        $product = filled($this->productId) ? $this->orderableProducts()->find((int) $this->productId) : null;

        $this->unitCost = $product ? number_format($product->cost, 2, '.', '') : '';
    }

    public function saveNotes(): void
    {
        $this->authorize('supplier_orders.edit');

        $this->validate([
            'quoteNumber' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->order->update([
            'quote_number' => filled($this->quoteNumber) ? $this->quoteNumber : null,
            'notes' => filled($this->notes) ? $this->notes : null,
        ]);

        Flux::toast(text: __('Informations sauvegardées.'), variant: 'success');
    }

    public function addLine(): void
    {
        $this->authorize('supplier_orders.edit');
        abort_unless($this->order->status->isEditable() || $this->order->status->isOpen(), 403);

        $rules = [
            'quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'unitCost' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];

        if ($this->order->isProductOrder()) {
            $rules['productId'] = [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('supplier_id', $this->order->supplier_id)
                    ->where('is_discontinued', 0)
                    ->where('is_non_orderable', 0),
            ];
        } else {
            $rules['description'] = ['required', 'string', 'max:255'];
        }

        $this->validate($rules);

        $this->order->addLine($this->order->isProductOrder()
            ? ['product_id' => $this->productId, 'quantity' => $this->quantity, 'unit_cost' => $this->unitCost]
            : ['description' => $this->description, 'quantity' => $this->quantity, 'unit_cost' => $this->unitCost]);

        $this->reset('productId', 'productSearch', 'description', 'unitCost');
        $this->quantity = '1';
        $this->order->unsetRelation('lines');

        $this->js("document.querySelector('[data-line-search] input')?.focus()");
    }

    public function startEditLine(int $lineId): void
    {
        $this->authorize('supplier_orders.edit');

        $line = $this->order->lines()->findOrFail($lineId);

        $this->editingLineId = $line->id;
        $this->editQuantity = (string) $line->quantity;
        $this->editUnitCost = number_format($line->unit_cost, 2, '.', '');
        $this->resetValidation();
    }

    public function cancelEditLine(): void
    {
        $this->reset('editingLineId', 'editQuantity', 'editUnitCost');
        $this->resetValidation();
    }

    public function saveLine(): void
    {
        $this->authorize('supplier_orders.edit');

        $this->validate([
            'editQuantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'editUnitCost' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $line = $this->order->lines()->findOrFail($this->editingLineId);

        $this->runTransition(
            fn () => $this->order->updateLine($line, (int) $this->editQuantity, (float) $this->editUnitCost),
            __('Ligne modifiée.'),
        );

        $this->cancelEditLine();
    }

    public function deleteOrder(): void
    {
        $this->authorize('supplier_orders.delete');

        if (! $this->order->isDeletable()) {
            Flux::toast(text: __('Seule une commande en brouillon ou en attente, sans ligne, peut être supprimée.'), variant: 'danger');

            return;
        }

        $this->order->delete();

        $this->redirectRoute('supplier-orders.index', navigate: true);
    }

    public function openSubstitute(int $lineId): void
    {
        $this->authorize('supplier_orders.edit');

        $this->substituteLineId = $this->order->lines()->findOrFail($lineId)->id;
        $this->substituteSearch = '';
        $this->substituteProductId = null;
        $this->showSubstitute = true;
    }

    public function selectSubstitute(int $productId): void
    {
        $this->authorize('supplier_orders.edit');

        $this->substituteProductId = $this->substitutionCandidates()->whereKey($productId)->firstOrFail()->id;
    }

    public function confirmSubstitute(): void
    {
        $this->authorize('supplier_orders.edit');

        $line = $this->order->lines()->findOrFail($this->substituteLineId);
        $product = $this->substitutionCandidates()->findOrFail($this->substituteProductId);

        $this->runTransition(fn () => $this->order->substituteLine($line, $product), __('Produit substitué.'));

        $this->showSubstitute = false;
        $this->substituteProductId = null;
    }

    /** @return Builder<Product> */
    private function substitutionCandidates(): Builder
    {
        $line = $this->order->lines()->findOrFail($this->substituteLineId);

        return Product::query()
            ->where('supplier_id', $this->order->supplier_id)
            ->where('is_discontinued', false)
            ->where('is_non_orderable', false)
            ->where('id', '!=', $line->product_id);
    }

    #[On('supplier-line-cancellation-requested')]
    public function onLineCancellationRequested(): void
    {
        $this->order->refresh()->unsetRelation('lines');
    }

    public function confirmLineCancellation(int $lineId): void
    {
        $this->authorize('supplier_orders.edit');

        $line = $this->order->lines()->findOrFail($lineId);

        $this->runTransition(fn () => $this->order->confirmLineCancellation($line), __('Annulation confirmée.'));
    }

    public function rejectLineCancellation(int $lineId): void
    {
        $this->authorize('supplier_orders.edit');

        $line = $this->order->lines()->findOrFail($lineId);

        $this->runTransition(fn () => $this->order->rejectLineCancellation($line), __('Annulation refusée : la ligne redevient active.'));
    }

    public function removeLine(int $lineId): void
    {
        $this->authorize('supplier_orders.edit');
        abort_unless($this->order->status->isEditable(), 403);

        $line = $this->order->lines()->with('customerOrderLine')->findOrFail($lineId);

        if ($line->customerOrderLine !== null) {
            Flux::toast(text: __('Cette ligne est liée à la commande client #:id : retirez-la ou réduisez sa quantité en commande dans la commande client.', ['id' => $line->customerOrderLine->customer_order_id]), variant: 'danger');

            return;
        }

        $line->delete();
        $this->order->unsetRelation('lines');
    }

    public function markPending(): void
    {
        $this->runTransition(fn () => $this->order->markPending(), __('Commande mise en attente.'));
    }

    public function send(): void
    {
        $notified = true;

        $this->runTransition(function () use (&$notified): void {
            $notified = $this->order->send();
        }, __('Commande envoyée.'));

        if (! $notified) {
            Flux::toast(text: __('Le courriel n\'a pas pu être envoyé au fournisseur (courriel manquant ou erreur d\'envoi) : transmettez la commande manuellement.'), variant: 'warning');
        }
    }

    public function resendEmail(): void
    {
        $this->authorize('supplier_orders.edit');

        abort_unless(SupplierOrder::emailEnabled() && $this->order->status->isOpen(), 403);

        if ($this->order->emailToSupplier()) {
            Flux::toast(text: __('Courriel renvoyé au fournisseur.'), variant: 'success');
        } else {
            Flux::toast(text: __('Le courriel n\'a pas pu être envoyé : vérifiez le courriel de commande du fournisseur.'), variant: 'danger');
        }
    }

    public function complete(): void
    {
        $this->runTransition(fn () => $this->order->complete(), __('Commande complétée.'));
    }

    public function cancel(): void
    {
        $this->runTransition(fn () => $this->order->cancel(), __('Commande annulée.'));
    }

    public function openReceive(): void
    {
        $this->authorize('supplier_orders.edit');

        $this->receipts = $this->order->lines->mapWithKeys(fn ($line) => [
            $line->id => ['quantity' => (string) $line->quantity_outstanding, 'damaged' => '0'],
        ])->all();

        $this->resetValidation();
        $this->showReceive = true;
    }

    public function receive(): void
    {
        $this->authorize('supplier_orders.edit');

        $this->validate([
            'receipts.*.quantity' => ['required', 'integer', 'min:0', 'max:99999'],
            'receipts.*.damaged' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $this->runTransition(fn () => $this->order->receive($this->receipts, auth()->id()), __('Réception enregistrée.'));

        $this->showReceive = false;
    }

    public function openReverse(int $receptionLineId): void
    {
        $this->authorize('receptions.reverse');

        $receptionLine = $this->order->completedReceptionLines()->findOrFail($receptionLineId);

        $this->reverseReceptionLineId = $receptionLine->id;
        $this->reverseQuantity = (string) $receptionLine->quantity_net;
        $this->reverseReason = '';
        $this->resetValidation();
        $this->showReverse = true;
    }

    public function reverseReceipt(): void
    {
        $this->authorize('receptions.reverse');

        $this->validate([
            'reverseQuantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'reverseReason' => ['nullable', 'string', 'max:255'],
        ]);

        $receptionLine = $this->order->completedReceptionLines()->findOrFail($this->reverseReceptionLineId);

        $this->runTransition(
            fn () => $receptionLine->reverse((int) $this->reverseQuantity, $this->reverseReason, auth()->id()),
            __('Réception renversée.'),
            'receptions.reverse',
        );

        $this->showReverse = false;
    }

    /** @return Builder<Product> */
    private function orderableProducts(): Builder
    {
        return Product::query()
            ->where('supplier_id', $this->order->supplier_id)
            ->where('is_discontinued', false)
            ->where('is_non_orderable', false);
    }

    /**
     * Produits commandables du fournisseur dont l'id, le modèle ou le modèle fournisseur correspond à la recherche.
     *
     * @return Collection<int, Product>
     */
    private function searchOrderableProducts(string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return new Collection;
        }

        return $this->orderableProducts()
            ->where(function ($query) use ($term) {
                $query->where('model', 'like', '%'.$term.'%')
                    ->orWhere('supplier_model', 'like', '%'.$term.'%');

                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
            })
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    private function runTransition(callable $action, string $successMessage, string $permission = 'supplier_orders.edit'): void
    {
        $this->authorize($permission);

        try {
            $action();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->order->refresh()->unsetRelation('lines');

        Flux::toast(text: $successMessage, variant: 'success');
    }

    public function render(): View
    {
        $this->order->load(['supplier', 'creator', 'lines.product', 'lines.customerOrderLine.order.customer']);

        $substituting = $this->showSubstitute && $this->substituteLineId !== null;

        return view('livewire.orders.show', [
            'invoices' => $this->order->isProductOrder()
                ? SupplierInvoice::query()
                    ->whereIn('reception_id', $this->order->completedReceptionLines()->pluck('reception_lines.reception_id'))
                    ->with('reception')
                    ->orderBy('id')
                    ->get()
                : SupplierInvoice::query()->where('supplier_order_id', $this->order->id)->get(),
            'receptionLines' => $this->order->isProductOrder()
                ? $this->order->completedReceptionLines()->with(['reception.invoice', 'orderLine.product'])->orderBy('id')->get()
                : collect(),
            'shippingSuppliers' => $this->order->isProductOrder()
                ? Supplier::query()
                    ->where('type', SupplierType::Shipping)
                    ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->order->shipping_supplier_id))
                    ->orderBy('name')
                    ->get()
                : collect(),
            'substitutedLine' => $substituting ? $this->order->lines->firstWhere('id', $this->substituteLineId) : null,
            'substituteProduct' => $substituting && $this->substituteProductId
                ? $this->substitutionCandidates()->find($this->substituteProductId)
                : null,
            'substituteResults' => $substituting && ! $this->substituteProductId
                ? $this->substitutionCandidates()
                    ->when(filled($this->substituteSearch), fn ($query) => $query->where(function ($q) {
                        $q->where('model', 'like', '%'.$this->substituteSearch.'%')
                            ->orWhere('supplier_model', 'like', '%'.$this->substituteSearch.'%');

                        if (ctype_digit(trim($this->substituteSearch))) {
                            $q->orWhere('id', (int) trim($this->substituteSearch));
                        }
                    }))
                    ->orderBy('model')
                    ->limit(10)
                    ->get()
                : collect(),
            'productResults' => $this->order->isProductOrder() && blank($this->productId)
                ? $this->searchOrderableProducts($this->productSearch)
                : new Collection,
            'selectedProduct' => $this->order->isProductOrder() && filled($this->productId)
                ? $this->orderableProducts()->find((int) $this->productId)
                : null,
        ])->layout('layouts.app', ['title' => $this->order->number]);
    }
}
