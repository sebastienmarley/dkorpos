<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Models\CustomerOrderDelivery;

/**
 * Avise une seule fois les responsables des expéditions qu'une expédition est à faire : quand tous ses articles sont
 * en main (en stock à la demande, ou à la réception du dernier article commandé).
 */
class NotifyShipmentReady
{
    public function __construct(private NotifyDeliveryTeam $notifyTeam) {}

    public function handle(CustomerOrderDelivery $shipment): void
    {
        if ($shipment->method !== DeliveryMethod::Shipping || $shipment->status !== DeliveryStatus::Scheduled || $shipment->ready_at !== null) {
            return;
        }

        if (! $shipment->hasAllItemsInHand()) {
            return;
        }

        $shipment->update(['ready_at' => now()]);

        $this->notifyTeam->handle(
            $shipment,
            __('Nouvelle expédition à faire'),
            $shipment->lines()->with('line.product', 'line.part')->get()
                ->map(fn ($shipmentLine): string => $shipmentLine->quantity.' × '.$shipmentLine->line->label)
                ->join(', '),
        );
    }
}
