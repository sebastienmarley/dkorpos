<?php

namespace App\Notifications;

use App\Models\CustomerOrderDelivery;
use Illuminate\Notifications\Notification;

/**
 * Avis aux livreurs sur une livraison de leur magasin : planifiée, reportée, annulée ou articles reçus avant la date.
 */
class DeliveryNotice extends Notification
{
    public const string Type = 'delivery';

    public function __construct(public CustomerOrderDelivery $delivery, public string $title, public string $body) {}

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
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => route('deliveries.index', ['date' => $this->delivery->scheduled_date?->toDateString()]),
            'icon' => 'truck',
        ];
    }
}
