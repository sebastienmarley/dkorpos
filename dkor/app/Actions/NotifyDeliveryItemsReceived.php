<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use Illuminate\Support\Carbon;

/**
 * Répercute la réception d'articles commandés pour un client sur ses livraisons : les livreurs sont avisés si la
 * livraison est à venir; une expédition dont tous les articles sont maintenant en main devient « à faire ».
 */
class NotifyDeliveryItemsReceived
{
    public function __construct(private NotifyDeliveryTeam $notifyTeam, private NotifyShipmentReady $notifyShipmentReady) {}

    public function handle(CustomerOrderLine $line, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        CustomerOrderDelivery::query()
            ->active()
            ->where('method', DeliveryMethod::Internal)
            ->whereDate('scheduled_date', '>=', Carbon::today())
            ->whereHas('lines', fn ($query) => $query->where('customer_order_line_id', $line->id))
            ->get()
            ->each(fn (CustomerOrderDelivery $delivery) => $this->notifyTeam->handle(
                $delivery,
                __('Articles reçus pour la livraison du :slot', ['slot' => $delivery->slotLabel()]),
                __(':quantity × :label', ['quantity' => $quantity, 'label' => $line->label]),
            ));

        CustomerOrderDelivery::query()
            ->active()
            ->where('method', DeliveryMethod::Shipping)
            ->whereNull('ready_at')
            ->whereHas('lines', fn ($query) => $query->where('customer_order_line_id', $line->id))
            ->get()
            ->each(fn (CustomerOrderDelivery $shipment) => $this->notifyShipmentReady->handle($shipment));
    }
}
