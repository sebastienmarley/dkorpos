<?php

namespace App\Notifications;

use App\Enums\CustomerOrderStatus;
use App\Models\CustomerOrder;
use Illuminate\Notifications\Notification;

/**
 * Avis aux vendeurs (cloche) : leur client a reçu toute sa commande, livrée ou ramassée (issue #137).
 */
class CustomerOrderHandedOverNotice extends Notification
{
    public const string Type = 'customer-order-handed-over';

    public function __construct(public CustomerOrder $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::Type;
    }

    /**
     * Forme commune des notifications affichées par la cloche.
     *
     * @return array{title: string, body: string, url: string, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        $customer = $this->order->customer;

        return [
            'title' => $this->order->status === CustomerOrderStatus::PickedUp
                ? __('Commande #:id ramassée au complet', ['id' => $this->order->id])
                : __('Commande #:id livrée au complet', ['id' => $this->order->id]),
            'body' => __(':customer a reçu tous ses articles.', ['customer' => $customer->firstname.' '.$customer->lastname]),
            'url' => route('customer-orders.show', $this->order),
            'icon' => 'check-circle',
        ];
    }
}
