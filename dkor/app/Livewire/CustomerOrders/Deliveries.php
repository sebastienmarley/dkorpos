<?php

namespace App\Livewire\CustomerOrders;

use App\Actions\CancelDelivery;
use App\Actions\RequestShipment;
use App\Actions\ScheduleDelivery;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use App\Support\DeliveryBooking;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livraisons et expéditions d'une commande client. Les deux méthodes partagent la vérification de l'adresse, le choix
 * des articles et l'exigence de paiement; une livraison se planifie ensuite à une demi-journée (reporter, annuler),
 * une expédition se demande (frais d'expédition proposés, payée à 100 %). Intégré à la page de la commande.
 */
class Deliveries extends Component
{
    /** Nombre de jours proposés dans le choix rapide d'une demi-journée. */
    public const int SUGGESTED_DAYS = 14;

    public CustomerOrder $order;

    public bool $showModal = false;

    /** Méthode en cours (valeur de DeliveryMethod) : livraison par nos camions ou expédition. */
    public string $method = 'internal';

    /** Ajouter les frais d'expédition à la commande avant de calculer le paiement exigé. */
    public bool $addShippingFee = true;

    /** Étape du modal : address, items, payment, slot (livraison) ou confirm (expédition). */
    public string $step = 'address';

    /** La fiche du client est ouverte pour corriger son adresse : la vérification reprend à sa sauvegarde. */
    public bool $isEditingAddress = false;

    /** Livraison planifiée en cours de report (on choisit seulement une nouvelle demi-journée). */
    public ?int $reschedulingId = null;

    /** @var array<int, int|string> quantité à livrer par ligne */
    public array $quantities = [];

    /** @var array<int, int> quantité livrable par ligne */
    public array $available = [];

    public int $requiredCents = 0;

    public bool $payOnDelivery = false;

    public string $date = '';

    public string $period = '';

    public string $notes = '';

    public function mount(CustomerOrder $order): void
    {
        $this->order = $order;
    }

    #[On('open-delivery-modal')]
    public function open(string $method = 'internal'): void
    {
        $this->authorize('customer_orders.edit');

        $this->reset(['reschedulingId', 'quantities', 'requiredCents', 'payOnDelivery', 'date', 'period', 'notes', 'isEditingAddress']);
        $this->resetErrorBag();
        $this->method = (DeliveryMethod::tryFrom($method) ?? DeliveryMethod::Internal)->value;
        $this->addShippingFee = $this->order->shippingFeeLines()->isEmpty();
        $this->available = $this->order->deliverableQuantities();

        if ($this->available === []) {
            Flux::toast(text: __('Aucun article à livrer : tout est déjà remis au client ou prévu dans une livraison.'), variant: 'warning');

            return;
        }

        $this->step = 'address';
        $this->showModal = true;
    }

    /** L'employé confirme avec le client l'adresse de sa fiche. */
    public function confirmAddress(): void
    {
        $this->authorize('customer_orders.edit');

        if (! $this->customer()->hasCompleteAddress()) {
            $this->addError('address', __('L\'adresse est incomplète : corrigez la fiche du client.'));

            return;
        }

        $this->quantities = $this->available;
        $this->step = 'items';
    }

    /** L'adresse n'est pas vérifiée : la fiche du client s'ouvre pour la corriger. */
    public function editAddress(): void
    {
        $this->authorize('customers.edit');

        $this->isEditingAddress = true;
        $this->showModal = false;
        $this->dispatch('open-customer-edit', id: $this->order->customer_id);
    }

    #[On('customer-saved')]
    public function onCustomerSaved(int $id): void
    {
        if (! $this->isEditingAddress || $id !== $this->order->customer_id) {
            return;
        }

        $this->isEditingAddress = false;
        $this->resetErrorBag();
        $this->step = 'address';
        $this->showModal = true;
    }

    /**
     * Valide les articles choisis et calcule ce qui reste à payer selon la règle du ramassage.
     */
    public function continueFromItems(): void
    {
        $this->authorize('customer_orders.edit');

        $this->validate([
            'quantities' => ['present', 'array'],
            'quantities.*' => ['required', 'integer', 'min:0'],
        ]);

        $quantities = $this->quantitiesAsIntegers();
        $this->available = $this->order->deliverableQuantities();

        foreach ($quantities as $lineId => $quantity) {
            if ($quantity > ($this->available[$lineId] ?? 0)) {
                $this->addError('quantities.'.$lineId, __('Maximum : :count', ['count' => $this->available[$lineId] ?? 0]));

                return;
            }
        }

        if (array_filter($quantities) === []) {
            $this->addError('quantities', __('Choisissez au moins un article à livrer.'));

            return;
        }

        if ($this->isShipping() && $this->addShippingFee && $this->order->shippingFeeLines()->isEmpty()) {
            try {
                $this->order->addShippingFee();
            } catch (DomainException $exception) {
                $this->addError('addShippingFee', $exception->getMessage());

                return;
            }

            $this->addShippingFee = false;
            $this->dispatch('order-lines-changed');
        }

        $required = $this->isShipping()
            ? $this->order->amountRequiredForShipment(array_filter($quantities))
            : $this->order->amountRequiredForDelivery(array_filter($quantities));
        $this->requiredCents = $required->cents();
        $this->payOnDelivery = false;
        $this->step = match (true) {
            $required->isPositive() => 'payment',
            $this->isShipping() => 'confirm',
            default => 'slot',
        };
    }

    public function isShipping(): bool
    {
        return $this->method === DeliveryMethod::Shipping->value;
    }

    /**
     * Avertissements des articles qui dépassent les limites d'expédition du magasin, par ligne.
     *
     * @return array<int, string>
     */
    public function getShippingWarnings(): array
    {
        $store = $this->order->store;

        if (! $this->isShipping() || $store === null) {
            return [];
        }

        return $this->getDeliverableLines()
            ->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => $line->product?->shippingWarningFor($store)])
            ->filter()
            ->all();
    }

    /** Demande l'expédition des articles choisis, une fois le paiement exigé encaissé. */
    public function requestShipment(RequestShipment $requestShipment): void
    {
        $this->authorize('customer_orders.edit');

        $this->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $shipment = $requestShipment->handle($this->order, array_filter($this->quantitiesAsIntegers()), $this->notes);
        } catch (DomainException $exception) {
            $this->addError('shipment', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->dispatch('deliveries-updated');

        Flux::toast(
            text: $shipment->ready_at === null
                ? __('Expédition demandée : elle sera à faire dès que tous les articles seront reçus.')
                : __('Expédition demandée : l\'entrepôt est avisé.'),
            variant: 'success',
        );
    }

    /** Le client règle la facture : le modal de ramassage permet d'encaisser le paiement. */
    public function settleInvoice(): void
    {
        $this->authorize('customer_orders.edit');

        $this->showModal = false;
        $this->dispatch('open-pickup-modal');
    }

    /** Un directeur outrepasse l'exigence de paiement : le solde exigé sera perçu à la livraison. */
    public function choosePayOnDelivery(): void
    {
        $this->authorize('customer_orders.deliver_unpaid');

        if ($this->isShipping()) {
            $this->addError('payment', __('Une expédition doit être payée à 100 % : personne ne peut encaisser chez le client.'));

            return;
        }

        $this->payOnDelivery = true;
        $this->step = 'slot';
    }

    public function backToItems(): void
    {
        $this->step = 'items';
    }

    public function pickSlot(string $date, string $period): void
    {
        $this->date = $date;
        $this->period = $period;
        $this->resetErrorBag(['date', 'period']);
    }

    public function confirm(ScheduleDelivery $scheduleDelivery): void
    {
        $this->authorize('customer_orders.edit');

        $this->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'period' => ['required', Rule::enum(DeliveryPeriod::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $replacing = $this->reschedulingId === null ? null : $this->order->deliveries()->findOrFail($this->reschedulingId);

        if ($replacing === null && $this->payOnDelivery) {
            $this->authorize('customer_orders.deliver_unpaid');
        }

        try {
            $delivery = $scheduleDelivery->handle(
                $this->order,
                $replacing === null ? array_filter($this->quantitiesAsIntegers()) : $replacing->lines()->pluck('quantity', 'customer_order_line_id')->all(),
                $this->date,
                DeliveryPeriod::from($this->period),
                $this->payOnDelivery,
                $this->notes,
                $replacing,
            );
        } catch (DomainException $exception) {
            $this->addError('date', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->dispatch('deliveries-updated');

        Flux::toast(
            text: $replacing === null
                ? __('Livraison planifiée : :slot.', ['slot' => $delivery->slotLabel()])
                : __('Livraison reportée au :slot.', ['slot' => $delivery->slotLabel()]),
            variant: 'success',
        );
    }

    /** Un report est une annulation suivie d'une nouvelle planification : on choisit la nouvelle demi-journée. */
    public function openReschedule(int $deliveryId): void
    {
        $this->authorize('customer_orders.edit');

        $delivery = $this->order->deliveries()->findOrFail($deliveryId);

        if ($delivery->status !== DeliveryStatus::Scheduled || $delivery->method !== DeliveryMethod::Internal) {
            Flux::toast(text: __('Seule une livraison planifiée peut être reportée.'), variant: 'danger');

            return;
        }

        $this->reset(['quantities', 'requiredCents', 'payOnDelivery', 'date', 'period', 'notes', 'isEditingAddress']);
        $this->resetErrorBag();
        $this->method = DeliveryMethod::Internal->value;
        $this->reschedulingId = $delivery->id;
        $this->step = 'slot';
        $this->showModal = true;
    }

    public function cancelDelivery(int $deliveryId, CancelDelivery $cancelDelivery): void
    {
        $this->authorize('customer_orders.edit');

        try {
            $cancelDelivery->handle($this->order->deliveries()->findOrFail($deliveryId));
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        $this->dispatch('deliveries-updated');

        Flux::toast(text: __('Livraison annulée.'), variant: 'success');
    }

    public function requiredAmount(): Money
    {
        return Money::of($this->requiredCents);
    }

    /**
     * Demi-journées des prochains jours avec leurs places, pour le choix rapide.
     *
     * @return array<string, array<string, array{capacity: int, booked: int, started: bool}>>
     */
    public function getSuggestedSlots(): array
    {
        if ($this->order->store_id === null) {
            return [];
        }

        return DeliveryBooking::slots($this->order->store_id, Carbon::today()->toDateString(), self::SUGGESTED_DAYS);
    }

    /** Places restantes pour la demi-journée choisie, ou null si elle n'est pas complète. */
    public function selectedSlotRemaining(): ?int
    {
        if ($this->order->store_id === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) || DeliveryPeriod::tryFrom($this->period) === null) {
            return null;
        }

        return DeliveryBooking::remaining($this->order->store_id, $this->date, DeliveryPeriod::from($this->period));
    }

    /** @return Collection<int, CustomerOrderDelivery> */
    public function getDeliveries(): Collection
    {
        return $this->order->deliveries()
            ->with(['lines.line.product', 'lines.line.part', 'requester', 'shippingSupplier'])
            ->orderByRaw('case when status in (?, ?) then 0 else 1 end', DeliveryStatus::activeValues())
            ->orderBy('scheduled_date')
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, CustomerOrderLine> */
    public function getDeliverableLines(): Collection
    {
        return $this->order->lines()->with(['product', 'part'])->whereKey(array_keys($this->available))->get();
    }

    private function customer(): customer
    {
        return $this->order->customer()->firstOrFail();
    }

    /** @return array<int, int> */
    private function quantitiesAsIntegers(): array
    {
        return array_map(fn (int|string $quantity): int => (int) $quantity, $this->quantities);
    }

    public function render(): View
    {
        return view('livewire.customer-orders.deliveries', [
            'customer' => $this->customer(),
            'deliveries' => $this->getDeliveries(),
            'rescheduling' => $this->reschedulingId === null ? null : $this->order->deliveries()->find($this->reschedulingId),
            'shippingWarnings' => $this->step === 'items' ? $this->getShippingWarnings() : [],
        ]);
    }
}
