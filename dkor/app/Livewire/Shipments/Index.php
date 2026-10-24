<?php

namespace App\Livewire\Shipments;

use App\Actions\ShipShipment;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\StoreType;
use App\Enums\SupplierType;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderDeliveryLine;
use App\Models\Store;
use App\Models\Supplier;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Menu d'expédition de l'entrepôt (issue #95) : les expéditions à faire du magasin (tous les articles en main
 * d'abord), le choix du transporteur et du coût de sa soumission, puis l'envoi avec le numéro de suivi. Les envois
 * récents restent visibles avec leur transporteur et leur suivi.
 */
class Index extends Component
{
    /** Nombre de jours pendant lesquels un envoi reste dans la liste des envois récents. */
    public const int RECENT_DAYS = 30;

    #[Url(as: 'store')]
    public string $storeId = '';

    public ?int $openedId = null;

    public string $carrierId = '';

    public string $shippingCost = '';

    public string $trackingNumber = '';

    /** @var array<int, int|string> quantité envoyée par ligne d'expédition */
    public array $sendQuantities = [];

    public function mount(): void
    {
        if ($this->storeId === '') {
            $this->storeId = (string) (auth()->user()->store_id ?? $this->getStores()->first()->id ?? '');
        }
    }

    public function open(int $shipmentId): void
    {
        $this->authorize('shipments.manage');

        $shipment = $this->findShipment($shipmentId);

        $this->openedId = $shipment->id;
        $this->carrierId = (string) ($shipment->shipping_supplier_id ?? '');
        $this->shippingCost = $shipment->shipping_cost?->toDecimal() ?? '';
        $this->trackingNumber = $shipment->tracking_number ?? '';
        $this->sendQuantities = $shipment->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $shipmentLine): array => [$shipmentLine->id => $this->inHand($shipmentLine)])->all();
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->openedId = null;
    }

    /** Le commis choisit le transporteur et inscrit le coût de sa soumission. */
    public function saveCarrier(): void
    {
        $this->authorize('shipments.manage');

        $this->shippingCost = str_replace(',', '.', $this->shippingCost);

        $this->validate([
            'carrierId' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('type', SupplierType::Shipping->value)->where('is_active', true)],
            'shippingCost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        try {
            $this->findShipment((int) $this->openedId)->chooseCarrier((int) $this->carrierId, Money::parse($this->shippingCost));
        } catch (DomainException $exception) {
            $this->addError('carrierId', $exception->getMessage());

            return;
        }

        Flux::toast(text: __('Transporteur enregistré.'), variant: 'success');
    }

    /** Le commis confirme l'envoi avec le numéro de suivi; ce qui n'est pas envoyé reste à faire. */
    public function ship(ShipShipment $shipShipment): void
    {
        $this->authorize('shipments.manage');

        $this->validate([
            'trackingNumber' => ['required', 'string', 'max:100'],
            'sendQuantities.*' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $remainder = $shipShipment->handle(
                $this->findShipment((int) $this->openedId),
                array_map(fn (int|string $quantity): int => (int) $quantity, $this->sendQuantities),
                $this->trackingNumber,
            );
        } catch (DomainException $exception) {
            $this->addError('ship', $exception->getMessage());

            return;
        }

        $this->openedId = null;
        Flux::toast(
            text: $remainder === null ? __('Expédition envoyée.') : __('Envoi partiel : le reste demeure une expédition à faire.'),
            variant: 'success',
        );
    }

    /** Quantité en main pour le client, qui peut partir maintenant. */
    public function inHand(CustomerOrderDeliveryLine $shipmentLine): int
    {
        $line = $shipmentLine->line;

        return $line->status->isPickable() ? min($shipmentLine->quantity, $line->quantity_reserved) : 0;
    }

    /** @return Collection<int, Store> */
    public function getStores(): Collection
    {
        return Store::query()->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Supplier> */
    public function getCarriers(): Collection
    {
        return Supplier::query()->where('type', SupplierType::Shipping)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Expéditions à faire du magasin : celles dont tous les articles sont en main d'abord.
     *
     * @return Collection<int, CustomerOrderDelivery>
     */
    public function getPending(): Collection
    {
        return $this->query()
            ->where('status', DeliveryStatus::Scheduled)
            ->orderByRaw('ready_at is null')
            ->orderBy('ready_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Envois des derniers jours, les plus récents d'abord.
     *
     * @return Collection<int, CustomerOrderDelivery>
     */
    public function getRecentlyShipped(): Collection
    {
        return $this->query()
            ->whereNotNull('shipped_at')
            ->where('shipped_at', '>=', now()->subDays(self::RECENT_DAYS))
            ->orderByDesc('shipped_at')
            ->get();
    }

    /** @return Builder<CustomerOrderDelivery> */
    private function query(): Builder
    {
        return CustomerOrderDelivery::query()
            ->with(['order.customer', 'order.store', 'lines.line.product', 'lines.line.part', 'shippingSupplier'])
            ->where('method', DeliveryMethod::Shipping)
            ->where('store_id', (int) $this->storeId);
    }

    private function findShipment(int $shipmentId): CustomerOrderDelivery
    {
        return $this->query()->findOrFail($shipmentId);
    }

    public function render(): View
    {
        $hasStore = $this->storeId !== '';
        $pending = $hasStore ? $this->getPending() : new Collection;

        return view('livewire.shipments.index', [
            'stores' => $this->getStores(),
            'pending' => $pending,
            'shipped' => $hasStore ? $this->getRecentlyShipped() : new Collection,
            'opened' => $this->openedId === null ? null : $pending->firstWhere('id', $this->openedId),
        ])->layout('layouts.app', ['title' => __('Expéditions')]);
    }
}
