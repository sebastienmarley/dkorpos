<?php

namespace App\Livewire\Inventory;

use App\Actions\AcceptStoreTransfer;
use App\Actions\CloseStoreTransfer;
use App\Actions\ReceiveStoreTransfer;
use App\Actions\RequestStoreTransfer;
use App\Enums\InventoryStatus;
use App\Enums\StoreTransferStatus;
use App\Enums\StoreType;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Menu « Transferts » d'un magasin (issue #103) : les transferts à accepter, ceux demandés par le magasin, ceux en
 * transit vers lui ou envoyés par lui, et les derniers fermés. Le magasin qui reçoit demande un transfert de stock.
 */
class Transfers extends Component
{
    /** Nombre de jours pendant lesquels un transfert fermé reste affiché. */
    public const int RECENT_DAYS = 30;

    #[Url(as: 'store')]
    public string $storeId = '';

    public bool $showRequest = false;

    public string $productSearch = '';

    public ?int $requestProductId = null;

    public string $fromStoreId = '';

    public string $quantity = '1';

    public bool $showRefuse = false;

    public ?int $refusingId = null;

    public string $refusalReason = '';

    public bool $showReceive = false;

    public ?int $receivingId = null;

    public string $receivedQuantity = '';

    public function mount(): void
    {
        abort_unless(Gate::any(['transfers.request', 'transfers.accept', 'transfers.receive']), 403);

        $user = auth()->user();
        $this->storeId = (string) ($user->store_id ?? ($this->storeId !== '' ? $this->storeId : Store::defaultIdFor($user)) ?? '');
    }

    public function openRequest(): void
    {
        $this->authorize('transfers.request');

        $this->reset(['productSearch', 'requestProductId', 'fromStoreId']);
        $this->quantity = '1';
        $this->resetValidation();
        $this->showRequest = true;
    }

    public function selectProduct(int $productId): void
    {
        $this->requestProductId = $productId;
        $this->fromStoreId = (string) ($this->getOtherStocks()->first()->store_id ?? '');
    }

    public function requestTransfer(RequestStoreTransfer $requestTransfer): void
    {
        $this->authorize('transfers.request');

        $this->validate([
            'requestProductId' => ['required', 'integer', 'exists:products,id'],
            'fromStoreId' => ['required', 'integer', 'exists:stores,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99999'],
        ]);

        try {
            $requestTransfer->handle(auth()->user(), Product::findOrFail($this->requestProductId), (int) $this->fromStoreId, (int) $this->quantity);
        } catch (DomainException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        }

        $this->showRequest = false;
        Flux::toast(text: __('Transfert demandé.'), variant: 'success');
    }

    public function accept(int $transferId, AcceptStoreTransfer $accept): void
    {
        $this->authorize('transfers.accept');

        $this->run(fn () => $accept->handle(auth()->user(), $this->findTransfer($transferId)), __('Transfert accepté : il est en transit.'));
    }

    public function openRefuse(int $transferId): void
    {
        $this->authorize('transfers.accept');

        $this->refusingId = $transferId;
        $this->refusalReason = '';
        $this->resetValidation();
        $this->showRefuse = true;
    }

    public function refuse(CloseStoreTransfer $close): void
    {
        $this->authorize('transfers.accept');

        $this->validate(['refusalReason' => ['required', 'string', 'max:255']]);

        $this->run(fn () => $close->handle(auth()->user(), $this->findTransfer((int) $this->refusingId), true, $this->refusalReason), __('Transfert refusé.'));
        $this->showRefuse = false;
    }

    public function cancel(int $transferId, CloseStoreTransfer $close): void
    {
        $this->authorize('transfers.request');

        $this->run(fn () => $close->handle(auth()->user(), $this->findTransfer($transferId), false), __('Transfert annulé.'));
    }

    public function openReceive(int $transferId): void
    {
        $this->authorize('transfers.receive');

        $this->receivingId = $transferId;
        $this->receivedQuantity = (string) $this->findTransfer($transferId)->quantity;
        $this->resetValidation();
        $this->showReceive = true;
    }

    public function receive(ReceiveStoreTransfer $receive): void
    {
        $this->authorize('transfers.receive');

        $this->validate(['receivedQuantity' => ['required', 'integer', 'min:0']]);

        $this->run(fn () => $receive->handle(auth()->user(), $this->findTransfer((int) $this->receivingId), (int) $this->receivedQuantity), __('Transfert reçu.'));
        $this->showReceive = false;
    }

    /**
     * Stock « En stock » du produit choisi dans les autres magasins.
     *
     * @return Collection<int, InventoryStock>
     */
    public function getOtherStocks(): Collection
    {
        if ($this->requestProductId === null) {
            return new Collection;
        }

        return InventoryStock::query()
            ->with('store')
            ->where('product_id', $this->requestProductId)
            ->where('store_id', '!=', (int) $this->storeId)
            ->where('quantity_in_stock', '>', 0)
            ->orderByDesc('quantity_in_stock')
            ->get();
    }

    /** @return Collection<int, Product> */
    public function getProductResults(): Collection
    {
        if (mb_strlen(trim($this->productSearch)) < 2) {
            return new Collection;
        }

        return Product::query()
            ->where('model', 'like', '%'.trim($this->productSearch).'%')
            ->whereHas('inventoryStocks', fn (Builder $stock) => $stock->where('store_id', '!=', (int) $this->storeId)->where('quantity_in_stock', '>', 0))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    /** @return Collection<int, Store> */
    public function getStores(): Collection
    {
        return Store::query()->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, StoreTransfer> */
    private function transfers(string $column, StoreTransferStatus $status): Collection
    {
        return StoreTransfer::query()
            ->with(['product', 'fromStore', 'toStore', 'line.order.customer', 'requester'])
            ->where($column, (int) $this->storeId)
            ->where('status', $status)
            ->orderBy('requested_at')
            ->get();
    }

    private function findTransfer(int $transferId): StoreTransfer
    {
        return StoreTransfer::query()
            ->where(fn (Builder $query) => $query->where('from_store_id', (int) $this->storeId)->orWhere('to_store_id', (int) $this->storeId))
            ->findOrFail($transferId);
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(text: $success, variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.inventory.transfers', [
            'stores' => $this->getStores(),
            'toAccept' => $this->transfers('from_store_id', StoreTransferStatus::Requested),
            'requested' => $this->transfers('to_store_id', StoreTransferStatus::Requested),
            'incoming' => $this->transfers('to_store_id', StoreTransferStatus::InTransit),
            'outgoing' => $this->transfers('from_store_id', StoreTransferStatus::InTransit),
            'closed' => StoreTransfer::query()
                ->with(['product', 'fromStore', 'toStore', 'line.order.customer'])
                ->where(fn (Builder $query) => $query->where('from_store_id', (int) $this->storeId)->orWhere('to_store_id', (int) $this->storeId))
                ->whereIn('status', [StoreTransferStatus::Received, StoreTransferStatus::Refused, StoreTransferStatus::Cancelled])
                ->where('updated_at', '>=', now()->subDays(self::RECENT_DAYS))
                ->latest('updated_at')
                ->get(),
            'selectedProduct' => $this->requestProductId ? Product::find($this->requestProductId) : null,
            'inStock' => InventoryStatus::InStock,
        ])->layout('layouts.app', ['title' => __('Transferts')]);
    }
}
