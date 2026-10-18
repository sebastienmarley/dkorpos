<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use Illuminate\Support\Carbon;

/**
 * Avise les livreurs quand des articles commandés pour un client arrivent avant la date de leur livraison planifiée.
 */
class NotifyDeliveryItemsReceived
{
    public function __construct(private NotifyDeliveryDrivers $notifyDrivers) {}

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
            ->each(fn (CustomerOrderDelivery $delivery) => $this->notifyDrivers->handle(
                $delivery,
                __('Articles reçus pour la livraison du :slot', ['slot' => $delivery->slotLabel()]),
                __(':quantity × :label', ['quantity' => $quantity, 'label' => $line->label]),
            ));
    }
}
