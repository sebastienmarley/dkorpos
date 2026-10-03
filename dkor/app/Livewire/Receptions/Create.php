<?php

namespace App\Livewire\Receptions;

use App\Enums\SupplierOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Create extends Component
{
    #[Url(as: 'supplier')]
    public string $supplierId = '';

    public string $orderSearch = '';

    public string $reference = '';

    public string $notes = '';

    /** @var array<int, bool> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $quantities = [];

    /** @var array<int, string> */
    public array $costs = [];

    public function updatedSupplierId(): void
    {
        $this->reset('selected', 'quantities', 'costs', 'orderSearch');
        $this->resetValidation();
    }

    /**
     * Cocher une ligne propose la quantité restante et son coût; la décocher efface la saisie.
     */
    public function updatedSelected(mixed $value, string $lineId): void
    {
        $line = $this->openLines()->get((int) $lineId);

        if ($value && $line) {
            $this->quantities[$line->id] = (string) $line->quantity_outstanding;
            $this->costs[$line->id] = number_format($line->unit_cost, 2, '.', '');
        } else {
            unset($this->selected[$lineId], $this->quantities[$lineId], $this->costs[$lineId]);
        }
    }

    public function save(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'costs.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $receipts = [];

        foreach (array_keys(array_filter($this->selected)) as $lineId) {
            $receipts[(int) $lineId] = [
                'quantity' => (int) ($this->quantities[$lineId] ?? 0),
                'unit_cost' => filled($this->costs[$lineId] ?? null) ? $this->costs[$lineId] : null,
            ];
        }

        try {
            $reception = Reception::record(Supplier::findOrFail($this->supplierId), $receipts, [
                'reference' => $this->reference,
                'notes' => $this->notes,
                'received_by' => auth()->id(),
            ]);
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('receptions.show', $reception, navigate: true);
    }

    /**
     * Lignes encore à recevoir des commandes de produits ouvertes du fournisseur choisi, indexées par id.
     *
     * @return \Illuminate\Support\Collection<int, SupplierOrderLine>
     */
    private function openLines(): \Illuminate\Support\Collection
    {
        return $this->openOrders()
            ->flatMap(fn (SupplierOrder $order) => $order->lines)
            ->keyBy('id');
    }

    /** @return Collection<int, SupplierOrder> */
    private function openOrders(): Collection
    {
        if (blank($this->supplierId)) {
            return new Collection;
        }

        return SupplierOrder::query()
            ->where('supplier_id', $this->supplierId)
            ->where('type', SupplierType::Product)
            ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived])
            ->when(filled($this->orderSearch), fn ($query) => $query->where(function ($q) {
                $q->where('number', 'like', '%'.$this->orderSearch.'%')
                    ->orWhere('quote_number', 'like', '%'.$this->orderSearch.'%');
            }))
            ->with(['lines' => fn ($query) => $query
                ->whereIn('status', [SupplierOrderLineStatus::Active, SupplierOrderLineStatus::CancellationRequested])
                ->whereColumn('quantity_received', '<', 'quantity'),
                'lines.product',
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (SupplierOrder $order) => $order->lines->isNotEmpty())
            ->values();
    }

    public function render(): View
    {
        return view('livewire.receptions.create', [
            'suppliers' => Supplier::query()
                ->where('type', SupplierType::Product)
                ->whereHas('orders', fn ($query) => $query
                    ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived]))
                ->orderBy('name')
                ->get(),
            'orders' => $this->openOrders(),
        ])->layout('layouts.app', ['title' => __('Nouvelle réception')]);
    }
}
