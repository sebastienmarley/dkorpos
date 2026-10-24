<?php

namespace App\Livewire\Deliveries;

use App\Actions\CompleteDelivery;
use App\Actions\FailDelivery;
use App\Actions\ReorderDeliveryRoute;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Enums\StoreType;
use App\Enums\UndeliveredReason;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderDeliveryLine;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryLocation;
use App\Models\Store;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Menu mobile du livreur (issue #94) : les livraisons d'une journée du magasin, dans l'ordre de la route, le
 * chargement du camion et la remise au client. La remise (issue #93) : le paiement à la livraison s'il y a lieu, la
 * liste des articles que le client coche, puis sa signature; ou une livraison non livrée (client absent, tout refusé).
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

    /**
     * Étape de la remise : '' (détail), checklist (articles à cocher), signature, ou refused (le client refuse tout).
     */
    public string $completionStep = '';

    /** @var array<int, bool> articles cochés (acceptés) par ligne de livraison */
    public array $acceptedChecks = [];

    /** @var array<int, int|string> quantité acceptée par ligne de livraison (une ligne de plusieurs unités) */
    public array $acceptedQuantities = [];

    public string $signerName = '';

    /** @var array<int, int|string> emplacement où l'article refusé est rangé au retour, par ligne de livraison */
    public array $returnLocations = [];

    /** @var array{method_id: int|string, amount: string} paiement perçu à la livraison */
    public array $payment = ['method_id' => '', 'amount' => ''];

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
        $this->acceptedChecks = $delivery->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $deliveryLine): array => [$deliveryLine->id => true])->all();
        $this->acceptedQuantities = $delivery->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $deliveryLine): array => [$deliveryLine->id => $deliveryLine->quantity])->all();
        $this->refusalReasons = [];
        $this->returnLocations = [];
        $this->completionStep = '';
        $this->signerName = trim($delivery->order->customer->firstname.' '.$delivery->order->customer->lastname);
        $this->payment = ['method_id' => $this->getDeliveryPaymentMethods()->first()->id ?? '', 'amount' => $this->amountToCollect($delivery)->toDecimal()];
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

    /** Coche ou décoche tous les articles (« Tout accepter »). */
    public function toggleAll(bool $accepted): void
    {
        $this->acceptedChecks = array_map(fn (): bool => $accepted, $this->acceptedChecks);
    }

    /** Le livreur enregistre le paiement perçu à la livraison, avec un mode « utilisable à la livraison ». */
    public function collectPayment(): void
    {
        $this->authorize('deliveries.handle');

        $this->payment['amount'] = str_replace(',', '.', (string) $this->payment['amount']);

        $this->validate([
            'payment.method_id' => ['required', 'integer', Rule::exists('customer_payment_methods', 'id')->where('is_active', true)->where('is_usable_on_delivery', true)],
            'payment.amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
        ]);

        $delivery = $this->findDelivery((int) $this->openedId);

        try {
            $delivery->order->pickUp([], [['method_id' => (int) $this->payment['method_id'], 'amount' => Money::parse($this->payment['amount'])]]);
        } catch (DomainException $exception) {
            $this->addError('payment', $exception->getMessage());

            return;
        }

        Flux::toast(text: __('Paiement enregistré.'), variant: 'success');
        $this->open($delivery->id);
    }

    public function startConfirmation(): void
    {
        $this->authorize('deliveries.handle');

        $this->resetErrorBag();
        $this->completionStep = 'checklist';
    }

    /** Le client confirme avoir reçu, en bon état, les articles cochés : il signe ensuite. */
    public function confirmReceipt(): void
    {
        $this->authorize('deliveries.handle');

        $delivery = $this->findDelivery((int) $this->openedId);
        $accepted = $this->acceptedByLine($delivery);

        if (array_filter($accepted) === []) {
            $this->addError('checklist', __('Cochez au moins un article reçu (sinon : « Le client refuse tout »).'));

            return;
        }

        foreach ($delivery->lines as $deliveryLine) {
            if ($accepted[$deliveryLine->id] < $deliveryLine->quantity && blank($this->refusalReasons[$deliveryLine->id] ?? null)) {
                $this->addError('refusalReasons.'.$deliveryLine->id, __('Indiquez la raison du refus.'));

                return;
            }
        }

        $this->resetErrorBag();
        $this->completionStep = 'signature';
    }

    /** Le client a signé et touche « Terminé » : la livraison est remise. */
    public function complete(string $signature, CompleteDelivery $completeDelivery): void
    {
        $this->authorize('deliveries.handle');

        $this->validate(['signerName' => ['required', 'string', 'max:255'], 'refusalReasons.*' => ['nullable', 'string', 'max:255']]);

        $delivery = $this->findDelivery((int) $this->openedId);

        try {
            $completeDelivery->handle($delivery, $this->acceptedByLine($delivery), $this->refusalReasons, $this->signerName, $signature, $this->returnLocationIds());
        } catch (DomainException $exception) {
            $this->addError('signature', $exception->getMessage());

            return;
        }

        $this->openedId = null;
        Flux::toast(text: __('Livraison terminée et signée.'), variant: 'success');
    }

    /** Ferme la livraison sans rien remettre : client absent, ou tout refusé (avec une raison par article). */
    public function markUndelivered(string $reason, FailDelivery $failDelivery): void
    {
        $this->authorize('deliveries.handle');

        $undeliveredReason = UndeliveredReason::from($reason);
        $delivery = $this->findDelivery((int) $this->openedId);

        try {
            $failDelivery->handle($delivery, $undeliveredReason, $this->refusalReasons, $this->returnLocationIds());
        } catch (DomainException $exception) {
            $this->addError('refused', $exception->getMessage());

            return;
        }

        $this->openedId = null;
        Flux::toast(text: __('Livraison non livrée : les articles reviennent au magasin.'), variant: 'warning');
    }

    /**
     * Emplacements actifs du magasin, pour ranger un article refusé au retour (issue #96).
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getReturnLocations(): Collection
    {
        return $this->storeId === '' ? new Collection : InventoryLocation::query()->ofStore((int) $this->storeId)->selectable()->with('zone')->orderBy('code')->get();
    }

    /**
     * Emplacements choisis pour le retour, par ligne de livraison (vides retirés).
     *
     * @return array<int, int>
     */
    private function returnLocationIds(): array
    {
        return array_map(fn (int|string $id): int => (int) $id, array_filter($this->returnLocations, fn (int|string $id): bool => filled($id)));
    }

    /** @return Collection<int, CustomerPaymentMethod> */
    public function getDeliveryPaymentMethods(): Collection
    {
        return CustomerPaymentMethod::query()->where('is_active', true)->where('is_usable_on_delivery', true)->orderBy('name')->get();
    }

    /**
     * Quantité acceptée par ligne de livraison : la quantité saisie pour un article coché, 0 sinon.
     *
     * @return array<int, int>
     */
    private function acceptedByLine(CustomerOrderDelivery $delivery): array
    {
        return $delivery->lines->mapWithKeys(fn (CustomerOrderDeliveryLine $deliveryLine): array => [
            $deliveryLine->id => ($this->acceptedChecks[$deliveryLine->id] ?? false)
                ? max(0, min($deliveryLine->quantity, (int) ($this->acceptedQuantities[$deliveryLine->id] ?? $deliveryLine->quantity)))
                : 0,
        ])->all();
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
            ->with(['order.customer', 'lines.line.product', 'lines.line.part', 'lines.line.location.zone'])
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
