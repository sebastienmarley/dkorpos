<?php

namespace App\Enums;

/**
 * Sortes d'avis envoyés à un client. Un avis automatique respecte le refus du client ; un envoi manuel, non.
 */
enum CustomerNoticeType: string
{
    case OrderSummary = 'order_summary';
    case DeliveryScheduled = 'delivery_scheduled';
    case DeliveryRescheduled = 'delivery_rescheduled';
    case DeliveryCancelled = 'delivery_cancelled';
    case DeliveryOnTheWay = 'delivery_on_the_way';
    case ShipmentSent = 'shipment_sent';
    case PickupReady = 'pickup_ready';

    public function label(): string
    {
        return match ($this) {
            self::OrderSummary => __('Commande envoyée'),
            self::DeliveryScheduled => __('Livraison planifiée'),
            self::DeliveryRescheduled => __('Livraison reportée'),
            self::DeliveryCancelled => __('Livraison annulée'),
            self::DeliveryOnTheWay => __('Livraison en chemin'),
            self::ShipmentSent => __('Expédition envoyée'),
            self::PickupReady => __('Prête à ramasser'),
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::OrderSummary;
    }
}
