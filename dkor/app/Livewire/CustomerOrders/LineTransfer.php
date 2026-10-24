<?php

namespace App\Livewire\CustomerOrders;

use App\Actions\RequestStoreTransfer;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\InventoryStatus;
use App\Models\CustomerOrderLine;
use App\Models\InventoryStock;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Transfert d'un autre magasin pour une ligne de commande client en commande (issue #103) : plutôt que de commander au
 * fournisseur, on demande le stock (ou le démonstrateur, pour la dernière unité) d'un autre magasin. Affiché dans le
 * modal de la ligne.
 */
class LineTransfer extends Component
{
    public int $lineId;

    /** Choix « magasin:état » (ex. : 3:in_stock). */
    public string $source = '';

    public string $quantity = '1';

    public function mount(int $lineId): void
    {
        $this->lineId = $lineId;
        $first = $this->getSources()->first();
        $this->source = $first['key'] ?? '';
        $this->quantity = (string) max(1, min($this->line()->quantity_on_order, $first['available'] ?? 1));
    }

    /** La ligne peut être servie par un transfert : produit du catalogue, en commande, pas déjà en transfert. */
    public function isEligible(): bool
    {
        $line = $this->line();

        return $line->product_id !== null && ! $line->is_custom && $line->part_id === null
            && in_array($line->status, [CustomerOrderLineStatus::OnOrder, CustomerOrderLineStatus::InStock], true)
            && $line->quantity_on_order > 0
            && ($line->supplierOrderLine === null || $line->supplierOrderLine->order->status->isEditable())
            && ! $line->transfers()->open()->exists();
    }

    /**
     * Magasins qui ont le produit en stock ou en démo (sauf celui qui sert la commande), avec la quantité.
     *
     * @return Collection<int, array{key: string, label: string, available: int}>
     */
    public function getSources(): Collection
    {
        $line = $this->line();

        return InventoryStock::query()
            ->with('store')
            ->where('product_id', $line->product_id)
            ->where('store_id', '!=', $line->order->stockStoreId())
            ->get()
            ->toBase()
            ->flatMap(fn (InventoryStock $stock): array => collect([InventoryStatus::InStock, InventoryStatus::InDemo])
                ->filter(fn (InventoryStatus $status): bool => $stock->quantityFor($status) > 0)
                ->map(fn (InventoryStatus $status): array => [
                    'key' => $stock->store_id.':'.$status->value,
                    'label' => (string) __(':store — :status : :count', ['store' => $stock->store->name, 'status' => mb_strtolower($status->label()), 'count' => $stock->quantityFor($status)]),
                    'available' => $stock->quantityFor($status),
                ])
                ->all())
            ->values();
    }

    public function request(RequestStoreTransfer $requestTransfer): void
    {
        $this->authorize('transfers.request');

        $this->validate([
            'source' => ['required', 'string', 'regex:/^\d+:(in_stock|in_demo)$/'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$storeId, $status] = explode(':', $this->source);
        $line = $this->line();

        try {
            $requestTransfer->handle(auth()->user(), $line->product, (int) $storeId, (int) $this->quantity, $line, InventoryStatus::from($status));
        } catch (DomainException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        }

        $this->dispatch('order-lines-changed');
        Flux::toast(text: __('Transfert demandé : la ligne ne sera pas commandée au fournisseur.'), variant: 'success');
    }

    private function line(): CustomerOrderLine
    {
        return CustomerOrderLine::query()->with(['order.store', 'product', 'supplierOrderLine.order'])->findOrFail($this->lineId);
    }

    public function render(): View
    {
        $eligible = $this->isEligible();

        return view('livewire.customer-orders.line-transfer', [
            'eligible' => $eligible,
            'sources' => $eligible ? $this->getSources() : collect(),
        ]);
    }
}
