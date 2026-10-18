<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Models\CustomerOrderDelivery;
use App\Models\User;
use App\Notifications\DeliveryNotice;
use App\Support\DeliveryBooking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Avise par la cloche l'équipe qui s'occupe d'une livraison : les livreurs actifs du magasin pour nos camions, les
 * responsables des expéditions (permission shipments.manage) pour une expédition.
 */
class NotifyDeliveryTeam
{
    public function handle(CustomerOrderDelivery $delivery, string $title, ?string $body = null): void
    {
        $recipients = $this->recipientsFor($delivery);

        if ($recipients->isEmpty()) {
            return;
        }

        $delivery->loadMissing('order.customer');
        $customer = $delivery->order->customer;

        $summary = __('Commande #:id — :customer, :city', [
            'id' => $delivery->customer_order_id,
            'customer' => $customer->firstname.' '.$customer->lastname,
            'city' => $delivery->address_city,
        ]);

        Notification::send($recipients, new DeliveryNotice($delivery, $title, $body === null ? $summary : $summary.' : '.$body));
    }

    /** @return Collection<int, User> */
    private function recipientsFor(CustomerOrderDelivery $delivery): Collection
    {
        if ($delivery->method === DeliveryMethod::Shipping) {
            return User::query()->where('is_active', true)->get()->filter(fn (User $user): bool => $user->can('shipments.manage'))->values();
        }

        return $delivery->store_id === null ? new Collection : DeliveryBooking::driversOf($delivery->store_id);
    }
}
