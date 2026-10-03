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

/**
 * Première étape d'une réception: on cherche des bons de commande (numéro ou quote #), on choisit les lignes à
 * recevoir avec leurs quantités, puis on commence la réception qui contient alors tous ces articles.
 */
class Create extends Component
{
    #[Url(as: 'supplier')]
    public string $supplierId = '';

    public string $orderSearch = '';

    /** @var array<int, bool> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $quantities = [];

    public function updatedSupplierId(): void
    {
        $this->reset('selected', 'quantities', 'orderSearch');
        $this->resetValidation();
    }

    /**
     * Cocher une ligne propose la quantité restante; la décocher efface la saisie. Livewire n'envoie pas toujours la
     * clé modifiée (mise à jour de tout le tableau): sans clé, toutes les lignes cochées sont synchronisées.
     */
    public function updatedSelected(mixed $value = null, ?string $lineId = null): void
    {
        foreach ($lineId === null ? array_keys($this->selected) : [$lineId] as $key) {
            $id = (int) $key;
            $line = ($this->selected[$id] ?? false) ? $this->receivableLine($id) : null;

            if ($line) {
                $this->quantities[$id] ??= (string) $line->quantity_outstanding;
            } else {
                unset($this->selected[$id], $this->quantities[$id]);
            }
        }
    }

    public function removeSelected(int $lineId): void
    {
        unset($this->selected[$lineId], $this->quantities[$lineId]);
    }

    public function start(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $receipts = [];

        foreach (array_keys(array_filter($this->selected)) as $lineId) {
            $receipts[(int) $lineId] = ['quantity' => (int) ($this->quantities[$lineId] ?? 0)];
        }

        try {
            $reception = Reception::start(Supplier::findOrFail($this->supplierId), $receipts, ['received_by' => auth()->id()]);
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('receptions.show', $reception, navigate: true);
    }

    /**
     * Ligne encore à recevoir d'une commande de produits ouverte du fournisseur choisi (null sinon).
     */
    private function receivableLine(int $lineId): ?SupplierOrderLine
    {
        if (blank($this->supplierId)) {
            return null;
        }

        return SupplierOrderLine::query()
            ->with(['order', 'product'])
            ->whereKey($lineId)
            ->whereIn('status', [SupplierOrderLineStatus::Active, SupplierOrderLineStatus::CancellationRequested])
            ->whereColumn('quantity_received', '<', 'quantity')
            ->whereHas('order', fn ($query) => $query
                ->where('supplier_id', $this->supplierId)
                ->where('type', SupplierType::Product)
                ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived]))
            ->first();
    }

    /**
     * Commandes ouvertes du fournisseur dont le numéro ou le quote # correspond à la recherche (rien sans recherche).
     *
     * @return Collection<int, SupplierOrder>
     */
    private function matchingOrders(): Collection
    {
        if (blank($this->supplierId) || blank(trim($this->orderSearch))) {
            return new Collection;
        }

        $term = trim($this->orderSearch);

        return SupplierOrder::query()
            ->where('supplier_id', $this->supplierId)
            ->where('type', SupplierType::Product)
            ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived])
            ->where(fn ($query) => $query
                ->where('number', 'like', '%'.$term.'%')
                ->orWhere('quote_number', 'like', '%'.$term.'%'))
            ->with(['lines' => fn ($query) => $query
                ->whereIn('status', [SupplierOrderLineStatus::Active, SupplierOrderLineStatus::CancellationRequested])
                ->whereColumn('quantity_received', '<', 'quantity'),
                'lines.product',
            ])
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->filter(fn (SupplierOrder $order) => $order->lines->isNotEmpty())
            ->values();
    }

    public function render(): View
    {
        $selectedIds = array_keys(array_filter($this->selected));

        return view('livewire.receptions.create', [
            'suppliers' => Supplier::query()
                ->where('type', SupplierType::Product)
                ->whereHas('orders', fn ($query) => $query
                    ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived]))
                ->orderBy('name')
                ->get(),
            'orders' => $this->matchingOrders(),
            'selectedLines' => $selectedIds === []
                ? new Collection
                : SupplierOrderLine::with(['order', 'product'])->whereKey($selectedIds)->get(),
        ])->layout('layouts.app', ['title' => __('Nouvelle réception')]);
    }
}
