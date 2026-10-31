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
 * Première étape d'une réception: on cherche des bons de commande (numéro ou quote #), on choisit les produits
 * attendus (les lignes d'un même produit sont regroupées), puis on commence la réception : tout ce qui reste à recevoir
 * y est attendu, et on compte ensuite ce qui est réellement reçu.
 *
 * Seules les commandes destinées au magasin de l'usager sont offertes (ADR 0013). Avec stores.all, les autres magasins
 * le sont aussi, mais la première ligne cochée d'une commande d'un autre magasin demande une confirmation.
 */
class Create extends Component
{
    #[Url(as: 'supplier')]
    public string $supplierId = '';

    public string $orderSearch = '';

    /** @var array<int, bool> */
    public array $selected = [];

    /** @var array<int, int> Commandes d'un autre magasin dont la réception a été confirmée. */
    public array $confirmedOrderIds = [];

    /** Ligne cochée qui attend la confirmation « commande d'un autre magasin ». */
    public ?int $pendingLineId = null;

    public bool $showStoreConfirm = false;

    public function updatedSupplierId(): void
    {
        $this->reset('selected', 'orderSearch', 'confirmedOrderIds', 'pendingLineId', 'showStoreConfirm');
        $this->resetValidation();
    }

    /**
     * Coche ou décoche ensemble les lignes d'un même produit d'une commande.
     *
     * @param  array<int, int>  $lineIds
     */
    public function toggleLines(array $lineIds): void
    {
        $lineIds = array_map('intval', $lineIds);
        $isChecked = ! in_array(false, array_map(fn (int $id): bool => $this->selected[$id] ?? false, $lineIds), true);

        foreach ($lineIds as $id) {
            $this->selected[$id] = ! $isChecked;
            $this->updatedSelected(! $isChecked, (string) $id);

            if ($this->showStoreConfirm) {
                return;
            }
        }
    }

    /**
     * Cocher une ligne vérifie qu'elle est encore à recevoir. Livewire n'envoie pas toujours la clé modifiée (mise à
     * jour de tout le tableau): sans clé, toutes les lignes cochées sont synchronisées.
     */
    public function updatedSelected(mixed $value = null, ?string $lineId = null): void
    {
        foreach ($lineId === null ? array_keys($this->selected) : [$lineId] as $key) {
            $id = (int) $key;
            $line = ($this->selected[$id] ?? false) ? $this->receivableLine($id) : null;

            if ($line && $this->needsStoreConfirmation($line)) {
                unset($this->selected[$id]);
                $this->pendingLineId = $id;
                $this->showStoreConfirm = true;
            } elseif ($line) {
                if ($key === $lineId && ($shipment = $line->activeShipment()) !== null) {
                    Flux::toast(
                        heading: __('Article pour expédition'),
                        text: __(':label est attendu pour l\'expédition de la commande #:order (:customer).', [
                            'label' => $line->label,
                            'order' => $shipment->customer_order_id,
                            'customer' => $shipment->order->customer->firstname.' '.$shipment->order->customer->lastname,
                        ]),
                        variant: 'warning',
                    );
                }
            } else {
                unset($this->selected[$id]);
            }
        }
    }

    /** L'usager confirme recevoir la commande d'un autre magasin : les lignes du produit en attente sont cochées. */
    public function confirmOtherStore(): void
    {
        $line = $this->pendingLineId !== null ? $this->receivableLine($this->pendingLineId) : null;
        $this->cancelOtherStore();

        if ($line === null) {
            return;
        }

        $this->confirmedOrderIds[] = $line->supplier_order_id;
        $this->toggleLines($this->sameProductLineIds($line));
    }

    /**
     * Lignes encore à recevoir de la commande pour le même produit que la ligne (la ligne seule sans produit).
     *
     * @return array<int, int>
     */
    private function sameProductLineIds(SupplierOrderLine $line): array
    {
        if ($line->product_id === null) {
            return [$line->id];
        }

        return $line->order->lines()
            ->where('product_id', $line->product_id)
            ->pluck('id')
            ->filter(fn (int $id): bool => $this->receivableLine($id) !== null)
            ->values()
            ->all();
    }

    public function cancelOtherStore(): void
    {
        $this->pendingLineId = null;
        $this->showStoreConfirm = false;
    }

    /** Commande destinée à un autre magasin que celui de l'usager, pas encore confirmée. */
    private function needsStoreConfirmation(SupplierOrderLine $line): bool
    {
        return $line->order->store_id !== auth()->user()->store_id && ! in_array($line->supplier_order_id, $this->confirmedOrderIds, true);
    }

    /**
     * @param  array<int, int>  $lineIds
     */
    public function removeSelected(array $lineIds): void
    {
        foreach ($lineIds as $lineId) {
            unset($this->selected[(int) $lineId]);
        }
    }

    public function start(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        $receipts = [];

        foreach (array_keys(array_filter($this->selected)) as $lineId) {
            $line = $this->receivableLine((int) $lineId);

            if ($line === null || $this->needsStoreConfirmation($line)) {
                Flux::toast(text: __('Une ligne choisie n\'est plus à recevoir pour votre magasin : vérifiez la sélection.'), variant: 'danger');

                return;
            }

            $receipts[(int) $lineId] = ['quantity' => 0, 'expected' => $line->quantity_outstanding];
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
            ->with(['order.store', 'product'])
            ->whereKey($lineId)
            ->whereIn('status', [SupplierOrderLineStatus::Active, SupplierOrderLineStatus::CancellationRequested])
            ->whereColumn('quantity_received', '<', 'quantity')
            ->whereHas('order', fn ($query) => $query
                ->where('supplier_id', (int) $this->supplierId)
                ->accessibleTo(auth()->user())
                ->where('type', SupplierType::Product)
                ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived]))
            ->first();
    }

    /**
     * Commandes ouvertes du fournisseur, destinées au magasin de l'usager (ADR 0013), dont le numéro ou le quote #
     * correspond à la recherche (rien sans recherche).
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
            ->where('supplier_id', (int) $this->supplierId)
            ->accessibleTo(auth()->user())
            ->where('type', SupplierType::Product)
            ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived])
            ->where(fn ($query) => $query
                ->whereLike('number', '%'.$term.'%')
                ->orWhereLike('quote_number', '%'.$term.'%'))
            ->with(['lines' => fn ($query) => $query
                ->whereIn('status', [SupplierOrderLineStatus::Active, SupplierOrderLineStatus::CancellationRequested])
                ->whereColumn('quantity_received', '<', 'quantity'),
                'lines.product',
                'lines.customerOrderLine',
                'store',
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
        $pendingLine = $this->pendingLineId !== null ? $this->receivableLine($this->pendingLineId) : null;

        return view('livewire.receptions.create', [
            'suppliers' => Supplier::query()
                ->where('type', SupplierType::Product)
                ->whereHas('orders', fn ($query) => $query
                    ->accessibleTo(auth()->user())
                    ->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived]))
                ->orderBy('name')
                ->get(),
            'orders' => $this->matchingOrders(),
            'pendingLine' => $pendingLine,
            'selectedLines' => $selectedIds === []
                ? new Collection
                : SupplierOrderLine::with(['order', 'product'])->whereKey($selectedIds)->get(),
        ])->layout('layouts.app', ['title' => __('Nouvelle réception')]);
    }
}
