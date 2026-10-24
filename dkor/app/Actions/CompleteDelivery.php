<?php

namespace App\Actions;

use App\Models\CustomerOrderDelivery;
use App\Models\User;
use App\Notifications\DeliveryNotice;
use DomainException;
use Illuminate\Support\Facades\Notification;

/**
 * Termine une livraison en route une fois la réception confirmée par le client (issue #94; la signature est à la
 * #93) : les articles acceptés sont livrés, les refusés retournent en stock client et les vendeurs de la commande
 * sont avisés pour s'occuper de leur client.
 */
class CompleteDelivery
{
    /**
     * @param  array<int, int>  $accepted  quantité acceptée par ligne de livraison
     * @param  array<int, string>  $refusalReasons  raison du refus par ligne de livraison
     *
     * @throws DomainException
     */
    public function handle(CustomerOrderDelivery $delivery, array $accepted, array $refusalReasons = []): void
    {
        $order = $delivery->order()->firstOrFail();
        $refused = $order->completeDelivery($delivery, $accepted, $refusalReasons);

        if ($refused === []) {
            return;
        }

        $order->loadMissing('customer');

        Notification::send(
            $order->salespeople()->where('is_active', true)->get()->reject(fn (User $user): bool => $user->id === auth()->id()),
            new DeliveryNotice(
                $delivery->fresh() ?? $delivery,
                __('Articles refusés à la livraison — commande #:id', ['id' => $order->id]),
                __(':customer : :items. Ils sont revenus en stock client.', [
                    'customer' => $order->customer->firstname.' '.$order->customer->lastname,
                    'items' => implode(', ', $refused),
                ]),
                route('customer-orders.show', $order),
            ),
        );
    }
}
