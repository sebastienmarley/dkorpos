<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OrderPlaced = 'order_placed';
    case OrderLineAdded = 'order_line_added';
    case OrderLineChanged = 'order_line_changed';
    case OrderLineCancelled = 'order_line_cancelled';
    case OrderCancelled = 'order_cancelled';
    case Substitution = 'substitution';
    case Receipt = 'receipt';
    case ReceiptDamaged = 'receipt_damaged';
    case ShortShipped = 'short_shipped';
    case ReceiptReversal = 'receipt_reversal';
    case Transfer = 'transfer';
    case CustomerReservation = 'customer_reservation';
    case CustomerReservationReleased = 'customer_reservation_released';
    case CustomerPickup = 'customer_pickup';
    case CustomerReturn = 'customer_return';
    case CustomerDefectiveReturn = 'customer_defective_return';
    case Adjustment = 'adjustment';
    case CountLostReturned = 'count_lost_returned';
    case DeliveryLoaded = 'delivery_loaded';
    case DeliveryRefused = 'delivery_refused';
    case CustomerDelivery = 'customer_delivery';
    case CustomerShipment = 'customer_shipment';
    case TransferRequested = 'transfer_requested';
    case TransferAccepted = 'transfer_accepted';
    case TransferReturned = 'transfer_returned';
    case TransferSent = 'transfer_sent';
    case TransferReceived = 'transfer_received';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => __('Commande envoyée'),
            self::OrderLineAdded => __('Ligne ajoutée'),
            self::OrderLineChanged => __('Quantité de ligne modifiée'),
            self::OrderLineCancelled => __('Ligne annulée'),
            self::OrderCancelled => __('Commande annulée'),
            self::Substitution => __('Substitution'),
            self::Receipt => __('Réception'),
            self::ReceiptDamaged => __('Réception endommagée'),
            self::ShortShipped => __('Manquant à la réception'),
            self::ReceiptReversal => __('Renversement de réception'),
            self::Transfer => __('Transfert manuel'),
            self::CustomerReservation => __('Réservation client'),
            self::CustomerReservationReleased => __('Réservation client libérée'),
            self::CustomerPickup => __('Ramassage client'),
            self::CustomerReturn => __('Retour client'),
            self::CustomerDefectiveReturn => __('Retour client défectueux'),
            self::Adjustment => __('Ajustement'),
            self::CountLostReturned => __('Perdu remis en stock (inventaire)'),
            self::DeliveryLoaded => __('Chargement de livraison'),
            self::DeliveryRefused => __('Refusé à la livraison'),
            self::CustomerDelivery => __('Livraison client'),
            self::CustomerShipment => __('Expédition client'),
            self::TransferRequested => __('Transfert demandé'),
            self::TransferAccepted => __('Transfert accepté (en transit)'),
            self::TransferReturned => __('Transfert refusé ou annulé'),
            self::TransferSent => __('Transfert sorti du magasin'),
            self::TransferReceived => __('Transfert reçu'),
        };
    }
}
