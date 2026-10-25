<?php

namespace App\Livewire\Accounting\Invoices;

use App\Enums\DeliveryMethod;
use App\Enums\SupplierType;
use App\Models\CustomerOrderDelivery;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Facture rattachée à un fournisseur seulement, sans lien avec une réception ni une commande. Pour un transporteur
 * (fournisseur d'expédition), on coche les envois qu'elle couvre et on détaille ses taxes (issue #95).
 */
class Create extends Component
{
    public string $supplierId = '';

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public string $description = '';

    public string $merchandiseTotal = '';

    public string $freightFee = '0';

    public string $customsFee = '0';

    public string $taxes = '0';

    public string $invoiceTotal = '';

    /** @var array<int, bool> envois cochés (transporteur), par id d'expédition */
    public array $shipmentIds = [];

    /** @var array<string, string> montant par taxe (transporteur), en dollars */
    public array $taxAmounts = [];

    public function updatedSupplierId(): void
    {
        $this->shipmentIds = [];
        $this->taxAmounts = $this->isCarrier()
            ? $this->carrierTaxNames()->mapWithKeys(fn (string $name): array => [$name => '0'])->all()
            : [];
    }

    /** Coche ou décoche un envoi; le montant avant taxes reprend la somme des coûts soumis des envois cochés. */
    public function updatedShipmentIds(): void
    {
        $this->merchandiseTotal = $this->selectedShipmentsCost()->toDecimal();
    }

    public function isCarrier(): bool
    {
        return filled($this->supplierId) && Supplier::query()->whereKey($this->supplierId)->where('type', SupplierType::Shipping)->exists();
    }

    /**
     * Envois du transporteur, partis et pas encore facturés.
     *
     * @return Collection<int, CustomerOrderDelivery>
     */
    public function getInvoiceableShipments(): Collection
    {
        if (! $this->isCarrier()) {
            return new Collection;
        }

        return CustomerOrderDelivery::query()
            ->with('order.customer')
            ->where('method', DeliveryMethod::Shipping)
            ->where('shipping_supplier_id', (int) $this->supplierId)
            ->whereNotNull('shipped_at')
            ->whereNull('supplier_invoice_id')
            ->orderBy('shipped_at')
            ->get();
    }

    public function selectedShipmentsCost(): Money
    {
        return Money::sum($this->getInvoiceableShipments()
            ->filter(fn (CustomerOrderDelivery $shipment): bool => (bool) ($this->shipmentIds[$shipment->id] ?? false))
            ->map(fn (CustomerOrderDelivery $shipment): Money => $shipment->shipping_cost ?? Money::zero())
            ->all());
    }

    public function taxTotal(): Money
    {
        return Money::sum(array_map(fn (string $amount): Money => Money::tryParse($amount) ?? Money::zero(), array_values($this->taxAmounts)));
    }

    /**
     * Taxes à détailler : celles en vigueur dans la province des magasins des envois (ou du magasin de l'usager).
     *
     * @return SupportCollection<int, string>
     */
    private function carrierTaxNames(): SupportCollection
    {
        $storeIds = $this->getInvoiceableShipments()->pluck('store_id')->filter()->unique();

        if ($storeIds->isEmpty() && auth()->user()->store_id !== null) {
            $storeIds = collect([auth()->user()->store_id]);
        }

        return Store::query()->whereKey($storeIds->all())->get()
            ->flatMap(fn (Store $store) => $store->applicableTaxNames())
            ->unique()
            ->values();
    }

    public function mount(): void
    {
        $this->invoiceDate = now()->toDateString();
    }

    public function save(): void
    {
        $this->authorize('invoices.create');

        if ($this->isCarrier()) {
            $this->saveCarrierInvoice();

            return;
        }

        $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', 1)],
            'invoiceNumber' => [
                'required', 'string', 'max:100',
                Rule::unique('supplier_invoices', 'invoice_number')->where('supplier_id', (int) $this->supplierId),
            ],
            'invoiceDate' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'merchandiseTotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'freightFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'customsFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'taxes' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'invoiceTotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        try {
            $invoice = SupplierInvoice::recordStandalone(Supplier::findOrFail($this->supplierId), [
                'invoice_number' => $this->invoiceNumber,
                'invoice_date' => $this->invoiceDate,
                'description' => $this->description,
                'merchandise_total' => $this->merchandiseTotal,
                'freight_fee' => $this->freightFee,
                'customs_fee' => $this->customsFee,
                'taxes' => $this->taxes,
                'invoice_total' => $this->invoiceTotal,
            ], auth()->id());
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('accounting.invoices.show', $invoice, navigate: true);
    }

    /** Facture d'un transporteur : les envois cochés, le montant avant taxes, chaque taxe et le total. */
    private function saveCarrierInvoice(): void
    {
        $this->validate([
            'invoiceNumber' => [
                'required', 'string', 'max:100',
                Rule::unique('supplier_invoices', 'invoice_number')->where('supplier_id', (int) $this->supplierId),
            ],
            'invoiceDate' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'merchandiseTotal' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'taxAmounts.*' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'invoiceTotal' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        try {
            $invoice = SupplierInvoice::recordForShipments(
                Supplier::findOrFail($this->supplierId),
                array_map('intval', array_keys(array_filter($this->shipmentIds))),
                [
                    'invoice_number' => $this->invoiceNumber,
                    'invoice_date' => $this->invoiceDate,
                    'description' => $this->description,
                    'merchandise_total' => $this->merchandiseTotal,
                    'invoice_total' => $this->invoiceTotal,
                ],
                $this->taxAmounts,
                auth()->id(),
            );
        } catch (DomainException $e) {
            $this->addError('shipmentIds', $e->getMessage());

            return;
        }

        $this->redirectRoute('accounting.invoices.show', $invoice, navigate: true);
    }

    public function render(): View
    {
        $supplier = filled($this->supplierId) ? Supplier::find($this->supplierId) : null;

        return view('livewire.accounting.invoices.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'totals' => $supplier ? SupplierInvoice::totals(
                $supplier,
                collect(),
                [],
                Money::tryParse($this->freightFee) ?? Money::zero(),
                Money::tryParse($this->customsFee) ?? Money::zero(),
                $this->isCarrier() ? $this->taxTotal() : (Money::tryParse($this->taxes) ?? Money::zero()),
                Money::tryParse($this->invoiceTotal) ?? Money::zero(),
                Carbon::canBeCreatedFromFormat($this->invoiceDate, 'Y-m-d') ? Carbon::parse($this->invoiceDate) : null,
                Money::tryParse($this->merchandiseTotal) ?? Money::zero(),
            ) : null,
        ])->layout('layouts.app', ['title' => __('Nouvelle facture')]);
    }
}
