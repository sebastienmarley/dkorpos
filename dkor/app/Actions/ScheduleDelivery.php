<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\Store;
use App\Support\DeliveryBooking;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Planifie la livraison d'articles d'une commande par nos camions, à une date et une demi-journée. Un report est
 * l'annulation de la livraison remplacée ($replacing) suivie d'une nouvelle planification, dans la même transaction :
 * l'adresse vérifiée et le paiement à la livraison sont repris.
 *
 * Les articles n'ont pas à être reçus, mais ils doivent être considérés payés selon la règle du ramassage (100 % des
 * articles livrés, dépôt sur le reste), à moins qu'un directeur n'accepte le paiement à la livraison.
 */
class ScheduleDelivery
{
    public function __construct(private NotifyDeliveryTeam $notifyTeam) {}

    /**
     * @param  array<int, int>  $quantities  quantité à livrer par ligne
     *
     * @throws DomainException si une règle de planification n'est pas respectée.
     */
    public function handle(
        CustomerOrder $order,
        array $quantities,
        string $date,
        DeliveryPeriod $period,
        bool $payOnDelivery = false,
        ?string $notes = null,
        ?CustomerOrderDelivery $replacing = null,
    ): CustomerOrderDelivery {
        if ($order->store_id === null) {
            throw new DomainException(__('La commande n\'est liée à aucun magasin : impossible de planifier ses camions.'));
        }

        if (Carbon::parse($date)->lt(Carbon::today())) {
            throw new DomainException(__('La date de livraison est passée.'));
        }

        $delivery = DB::transaction(function () use ($order, $quantities, $date, $period, $payOnDelivery, $notes, $replacing): CustomerOrderDelivery {
            Store::query()->whereKey($order->store_id)->lockForUpdate()->firstOrFail();

            if ($replacing !== null) {
                $this->cancelReplaced($order, $replacing, $date, $period);
            }

            $quantities = $order->guardDeliverableQuantities($quantities);

            if (DeliveryBooking::hasStarted($date, $period)) {
                throw new DomainException(__('Cette demi-journée est déjà commencée.'));
            }

            if (DeliveryBooking::remaining($order->store_id, $date, $period) < 1) {
                throw new DomainException(__('Plus aucune place de livraison pour cette demi-journée.'));
            }

            $required = $order->amountRequiredForDelivery($quantities);
            $carriesPayOnDelivery = $replacing !== null && $replacing->pay_on_delivery;
            $payOnDelivery = $required->isPositive() && ($payOnDelivery || $carriesPayOnDelivery);

            if ($required->isPositive() && ! $payOnDelivery) {
                throw new DomainException(__('Réglez la facture avant de planifier la livraison : :amount $ à payer.', ['amount' => $required->toDecimal()]));
            }

            $delivery = $order->deliveries()->create([
                ...$this->address($order, $replacing),
                'store_id' => $order->store_id,
                'rescheduled_from_id' => $replacing?->id,
                'method' => DeliveryMethod::Internal,
                'status' => DeliveryStatus::Scheduled,
                'scheduled_date' => $date,
                'period' => $period,
                'pay_on_delivery' => $payOnDelivery,
                'pay_on_delivery_authorized_by' => match (true) {
                    ! $payOnDelivery => null,
                    $carriesPayOnDelivery => $replacing->pay_on_delivery_authorized_by,
                    default => auth()->id(),
                },
                'notes' => filled($notes) ? $notes : $replacing?->notes,
                'requested_by' => auth()->id(),
            ]);

            foreach ($quantities as $lineId => $quantity) {
                $delivery->lines()->create(['customer_order_line_id' => $lineId, 'quantity' => $quantity]);
            }

            return $delivery;
        });

        $this->notifyTeam->handle(
            $delivery,
            $replacing === null
                ? __('Livraison planifiée : :slot', ['slot' => $delivery->slotLabel()])
                : __('Livraison reportée au :slot (prévue le :previous)', ['slot' => $delivery->slotLabel(), 'previous' => $replacing->slotLabel()]),
        );

        return $delivery;
    }

    private function cancelReplaced(CustomerOrder $order, CustomerOrderDelivery $replacing, string $date, DeliveryPeriod $period): void
    {
        $replacing = CustomerOrderDelivery::query()->whereKey($replacing->id)->lockForUpdate()->firstOrFail();

        if ($replacing->customer_order_id !== $order->id || $replacing->status !== DeliveryStatus::Scheduled) {
            throw new DomainException(__('Seule une livraison planifiée de cette commande peut être reportée.'));
        }

        $replacing->update([
            'status' => DeliveryStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => __('Reportée au :date :period', ['date' => Carbon::parse($date)->translatedFormat('D j M Y'), 'period' => $period->label()]),
        ]);
    }

    /**
     * Adresse vérifiée avec le client (sa fiche) ou celle de la livraison reportée.
     *
     * @return array<string, mixed>
     */
    private function address(CustomerOrder $order, ?CustomerOrderDelivery $replacing): array
    {
        if ($replacing === null) {
            return $order->verifiedDeliveryAddress();
        }

        return [
            ...$replacing->only(CustomerOrderDelivery::ADDRESS_FIELDS),
            'address_verified_by' => $replacing->address_verified_by,
            'address_verified_at' => $replacing->address_verified_at,
        ];
    }
}
