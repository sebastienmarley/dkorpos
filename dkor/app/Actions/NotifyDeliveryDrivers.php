<?php

namespace App\Actions;

use App\Models\CustomerOrderDelivery;
use App\Notifications\DeliveryNotice;
use App\Support\DeliveryBooking;
use Illuminate\Support\Facades\Notification;

/**
 * Avise par la cloche les livreurs actifs du magasin qui fait la livraison.
 */
class NotifyDeliveryDrivers
{
    public function handle(CustomerOrderDelivery $delivery, string $title, ?string $body = null): void
    {
        if ($delivery->store_id === null) {
            return;
        }

        $delivery->loadMissing('order.customer');
        $customer = $delivery->order->customer;

        $summary = __('Commande #:id — :customer, :city', [
            'id' => $delivery->customer_order_id,
            'customer' => $customer->firstname.' '.$customer->lastname,
            'city' => $delivery->address_city,
        ]);

        Notification::send(
            DeliveryBooking::driversOf($delivery->store_id),
            new DeliveryNotice($delivery, $title, $body === null ? $summary : $summary.' : '.$body),
        );
    }
}
