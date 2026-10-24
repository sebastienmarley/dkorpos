<?php

namespace App\Livewire\Deliveries;

use App\Actions\CompleteDelivery;
use App\Actions\ReorderDeliveryRoute;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Enums\StoreType;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderDeliveryLine;
use App\Models\Store;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Menu mobile du livreur (issue #94) : les livraisons d'une journée du magasin, dans l'ordre de la route, le
 * chargement du camion et la remise au client (articles acceptés ou refusés).
 */
class Mine extends Component
{
    #[Url]
    public string $date = '';

    #[Url(as: 'store')]
    public string $storeId = '';

    /** Livraison ouverte (détail, chargement ou remise). */
    public ?int $openedId = null;

    /** @var array<int, int|string> quantité chargée par ligne de livraison */
    public array $loadQuantities = [];

    /** @var array<int, int|string> quantité acceptée par ligne de livraison */
    public array $acceptedQuantities = [];

    /** @var array<int, string> raison du refus par ligne de livraison */
    public array $refusalReasons = [];

    public function mount(): void
    {
        $this->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? $this->date : Carbon::today()->toDateString();

        if ($this->storeId === '') {
            $this->storeId = (string) (auth()->user()->store_id ?? $this->getStores()->first()->id ?? '');
        }
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
        $this->openedId = null;
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
        $this->openedId = null;
    }

    public function goToToday(): void
    {
        $this->date = Carbon::today()->toDateString();
        $this->openedId = null;
    }

    /**
     * Déplace une livraison d'un rang dans la route de sa demi-journée (-1 : plus tôt, 1 : plus tard).
     */
    public function move(int $deliveryId, int $direction, ReorderDeliveryRoute $reorder): void
    {
        $this->authorize('deliveries.handle');

        $delivery = $this->findDelivery($deliveryId);
        $ids = $this->getDeliveries()
            ->filter(fn (CustomerOrderDelivery $other): bool => $other->period === $delivery->period && $other->status->isActive())
            ->values()
            ->modelKeys();
        $index = array_search($delivery->id, $ids, true);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($target === null || ! isset($ids[$target])) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        try {
            $reorder->handle((int) $this->storeId, $this->date, $delivery->period, array_values($ids));
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');
        }
    }

    public function open(int $deliveryId): void
    {
        $this->authorize('deliveries.handle');

        $delivery = $this->findDelivery($deliveryId);

        $this->openedId = $delivery->id;
        $this->loadQuantities = $delivery->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $deliveryLine): array => [$deliveryLine->id => $this->loadable($deliveryLine)])->all();
        $this->acceptedQuantities = $delivery->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $deliveryLine): array => [$deliveryLine->id => $deliveryLine->quantity])->all();
        $this->refusalReasons = [];
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->openedId = null;
    }

    /** Le livreur confirme le départ avec les articles chargés. */
    public function depart(): void
    {
        $this->authorize('deliveries.handle');

        $this->validate(['loadQuantities.*' => ['required', 'integer', 'min:0']]);

        $delivery = $this->findDelivery((int) $this->openedId);

        try {
            $delivery->order->loadDelivery($delivery, array_map(fn (int|string $quantity): int => (int) $quantity, $this->loadQuantities));
        } catch (DomainException $exception) {
            $this->addError('load', $exception->getMessage());

            return;
        }

        Flux::toast(text: __('Départ confirmé : la livraison est en route.'), variant: 'success');
        $this->open($delivery->id);
    }

    /** Le client a reçu les articles : ceux qu'il accepte sont livrés, les autres reviennent au magasin. */
    public function complete(CompleteDelivery $completeDelivery): void
    {
        $this->authorize('deliveries.handle');

        $this->validate([
            'acceptedQuantities.*' => ['required', 'integer', 'min:0'],
            'refusalReasons.*' => ['nullable', 'string', 'max:255'],
        ]);

        $delivery = $this->findDelivery((int) $this->openedId);

        try {
            $completeDelivery->handle($delivery, array_map(fn (int|string $quantity): int => (int) $quantity, $this->acceptedQuantities), $this->refusalReasons);
        } catch (DomainException $exception) {
            $this->addError('complete', $exception->getMessage());

            return;
        }

        $this->openedId = null;
        Flux::toast(text: __('Livraison terminée.'), variant: 'success');
    }

    /** Montant que le livreur doit percevoir avant de remettre les articles (paiement à la livraison). */
    public function amountToCollect(CustomerOrderDelivery $delivery): Money
    {
        if (! $delivery->pay_on_delivery || ! $delivery->status->isActive()) {
            return Money::zero();
        }

        return $delivery->order->amountRequiredFor($delivery->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $deliveryLine): array => [$deliveryLine->customer_order_line_id => $deliveryLine->quantity])->all());
    }

    /** Quantité qui peut être chargée : ce qui est prévu, dans la limite de ce qui est en main pour le client. */
    public function loadable(CustomerOrderDeliveryLine $deliveryLine): int
    {
        $line = $deliveryLine->line;

        return $line->status->isPickable() ? min($deliveryLine->quantity, $line->quantity_reserved) : 0;
    }

    /**
     * Articles encore en commande d'une livraison planifiée (« 1 × Modèle »), vide si tout est en main.
     *
     * @return list<string>
     */
    public function missingItems(CustomerOrderDelivery $delivery): array
    {
        if ($delivery->status !== DeliveryStatus::Scheduled) {
            return [];
        }

        return array_values($delivery->lines
            ->filter(fn (CustomerOrderDeliveryLine $deliveryLine): bool => $this->loadable($deliveryLine) < $deliveryLine->quantity)
            ->map(fn (CustomerOrderDeliveryLine $deliveryLine): string => ($deliveryLine->quantity - $this->loadable($deliveryLine)).' × '.$deliveryLine->line->label)
            ->all());
    }

    /** Lien d'itinéraire vers l'adresse, ouvert par l'application de cartes du téléphone. */
    public function directionsUrl(CustomerOrderDelivery $delivery): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($delivery->formattedAddress());
    }

    /** @return Collection<int, Store> */
    public function getStores(): Collection
    {
        return Store::query()->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Livraisons de la journée (planifiées, en route ou livrées), dans l'ordre de la route puis de planification.
     *
     * @return Collection<int, CustomerOrderDelivery>
     */
    public function getDeliveries(): Collection
    {
        return CustomerOrderDelivery::query()
            ->with(['order.customer', 'lines.line.product', 'lines.line.part'])
            ->where('method', DeliveryMethod::Internal)
            ->where('store_id', (int) $this->storeId)
            ->whereDate('scheduled_date', $this->date)
            ->where('status', '!=', DeliveryStatus::Cancelled)
            ->orderByRaw('route_position is null')
            ->orderBy('route_position')
            ->orderBy('id')
            ->get();
    }

    private function findDelivery(int $deliveryId): CustomerOrderDelivery
    {
        return CustomerOrderDelivery::query()
            ->with(['order.customer', 'lines.line.product', 'lines.line.part'])
            ->where('method', DeliveryMethod::Internal)
            ->where('store_id', (int) $this->storeId)
            ->findOrFail($deliveryId);
    }

    public function render(): View
    {
        $deliveries = $this->storeId === '' ? new Collection : $this->getDeliveries();

        return view('livewire.deliveries.mine', [
            'stores' => $this->getStores(),
            'byPeriod' => collect(DeliveryPeriod::cases())->mapWithKeys(fn (DeliveryPeriod $period): array => [
                $period->value => $deliveries->filter(fn (CustomerOrderDelivery $delivery): bool => $delivery->period === $period)->values(),
            ]),
            'opened' => $this->openedId === null ? null : $deliveries->firstWhere('id', $this->openedId),
        ])->layout('layouts.app', ['title' => __('Mes livraisons')]);
    }
}
