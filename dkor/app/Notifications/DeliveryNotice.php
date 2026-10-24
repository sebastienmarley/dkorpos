<?php

namespace App\Notifications;

use App\Enums\DeliveryMethod;
use App\Models\CustomerOrderDelivery;
use Illuminate\Notifications\Notification;

/**
 * Avis sur une livraison (aux livreurs) ou une expédition (aux responsables des expéditions) : planifiée, reportée,
 * modifiée, annulée, articles reçus ou expédition prête.
 */
class DeliveryNotice extends Notification
{
    public const string Type = 'delivery';

    /**
     * @param  string|null  $url  lien de l'avis (par défaut : la page des livraisons, à la date ou aux expéditions)
     */
    public function __construct(public CustomerOrderDelivery $delivery, public string $title, public string $body, public ?string $url = null) {}

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
            'url' => $this->url ?? ($this->delivery->method === DeliveryMethod::Shipping
                ? route('deliveries.index').'#expeditions'
                : route('deliveries.index', ['date' => $this->delivery->scheduled_date?->toDateString()])),
            'icon' => 'truck',
        ];
    }
}
