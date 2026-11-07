<?php

namespace App\Notifications\Customers;

use App\Enums\CustomerNoticeType;
use App\Models\customer;
use App\Models\CustomerOrderDelivery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avis au client sur sa livraison ou son expédition (issue #136) : planifiée, reportée, annulée, en chemin, envoyée.
 */
class DeliveryUpdateNotice extends Notification
{
    public function __construct(public CustomerOrderDelivery $delivery, public CustomerNoticeType $type) {}

    /** @return list<string> */
    public function via(customer $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(customer $notifiable): MailMessage
    {
        $delivery = $this->delivery->loadMissing(['order.store', 'shippingSupplier', 'rescheduledFrom']);
        $store = $delivery->order->store;
        $orderNumber = $delivery->order->id;

        $message = (new MailMessage)
            ->subject($this->type->label().' — '.__('commande #:id', ['id' => $orderNumber]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->firstname]));

        if (filled($store?->email)) {
            $message->replyTo($store->email, $store->name);
        }

        match ($this->type) {
            CustomerNoticeType::DeliveryScheduled => $message
                ->line(__('La livraison de votre commande #:id est planifiée le :slot.', ['id' => $orderNumber, 'slot' => $delivery->slotLabel()]))
                ->line(__('Adresse de livraison : :address', ['address' => $delivery->formattedAddress()]))
                ->line(__('Assurez-vous qu\'une personne soit présente pour recevoir la livraison.')),
            CustomerNoticeType::DeliveryRescheduled => $message
                ->line(__('La livraison de votre commande #:id est reportée au :slot.', ['id' => $orderNumber, 'slot' => $delivery->slotLabel()]))
                ->lineIf($delivery->rescheduledFrom !== null, __('Elle était prévue le :previous.', ['previous' => $delivery->rescheduledFrom?->slotLabel()]))
                ->line(__('Adresse de livraison : :address', ['address' => $delivery->formattedAddress()])),
            CustomerNoticeType::DeliveryCancelled => $message
                ->line(__('La livraison de votre commande #:id prévue le :slot est annulée.', ['id' => $orderNumber, 'slot' => $delivery->slotLabel()]))
                ->line(__('Nous communiquerons avec vous pour convenir d\'une autre date.')),
            CustomerNoticeType::DeliveryOnTheWay => $message
                ->line(__('Votre livraison est en chemin! Notre équipe se dirige vers le :address.', ['address' => $delivery->formattedAddress()])),
            CustomerNoticeType::ShipmentSent => $message
                ->line(__('Votre commande #:id a été expédiée.', ['id' => $orderNumber]))
                ->lineIf($delivery->shippingSupplier !== null, __('Transporteur : :carrier', ['carrier' => $delivery->shippingSupplier?->name]))
                ->lineIf(filled($delivery->tracking_number), __('Numéro de suivi : :tracking', ['tracking' => $delivery->tracking_number])),
            default => $message,
        };

        if ($store !== null) {
            $message->line(__('Pour toute question, communiquez avec :store:contact.', [
                'store' => $store->name,
                'contact' => filled($store->phone) ? ' '.__('au :phone', ['phone' => $store->phone]) : '',
            ]));
        }

        return $message->salutation(__('Merci de votre confiance!'));
    }
}
