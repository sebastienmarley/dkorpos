<?php

namespace App\Actions;

use App\Models\CustomerOrderDelivery;
use App\Models\User;
use App\Notifications\DeliveryNotice;
use Illuminate\Support\Facades\Notification;

/**
 * Avise les vendeurs de la commande que des articles sont revenus de livraison (refusés, ou client absent), pour
 * qu'ils s'occupent de leur client. L'usager qui ferme la livraison n'est pas avisé.
 */
class NotifyDeliverySalespeople
{
    /**
     * @param  list<string>  $items  articles revenus au magasin (« 1 × Modèle : raison »)
     */
    public function handle(CustomerOrderDelivery $delivery, string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $order = $delivery->order()->with('customer')->firstOrFail();

        Notification::send(
            $order->salespeople()->where('is_active', true)->get()->reject(fn (User $user): bool => $user->id === auth()->id()),
            new DeliveryNotice(
                $delivery,
                $title,
                __(':customer : :items. Ils sont revenus en stock client.', [
                    'customer' => $order->customer->firstname.' '.$order->customer->lastname,
                    'items' => implode(', ', $items),
                ]),
                route('customer-orders.show', $order),
            ),
        );
    }
}
