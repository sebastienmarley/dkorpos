<?php

namespace App\Enums;

enum CustomerOrderLineStatus: string
{
    case InStock = 'in_stock';
    case ToDo = 'to_do';
    case OnOrder = 'on_order';
    case Ordered = 'ordered';
    case Received = 'received';
    case InDelivery = 'in_delivery';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Returned = 'returned';
    case Refunded = 'refunded';
    case Shipped = 'shipped';
    case CancellationRequested = 'cancellation_requested';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('En inventaire'),
            self::ToDo => __('À faire'),
            self::OnOrder => __('En commande'),
            self::Ordered => __('Commandé'),
            self::Received => __('Reçu'),
            self::InDelivery => __('En livraison'),
            self::Delivered => __('Livré'),
            self::PickedUp => __('Ramassé'),
            self::Returned => __('Retourné'),
            self::Refunded => __('Remboursé'),
            self::Shipped => __('Expédié'),
            self::CancellationRequested => __('Demande d\'annulation'),
            self::Cancelled => __('Annulé'),
            self::Completed => __('Complété'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'zinc',
            self::ToDo => 'zinc',
            self::OnOrder => 'yellow',
            self::Ordered => 'sky',
            self::Received => 'lime',
            self::InDelivery, self::Shipped => 'blue',
            self::Delivered, self::PickedUp => 'green',
            self::Returned => 'orange',
            self::Refunded => 'purple',
            self::CancellationRequested => 'amber',
            self::Cancelled => 'red',
            self::Completed => 'emerald',
        };
    }

    /** Une ligne peut être modifiée ou retirée tant que la marchandise n'est pas reçue ni sortie (ou le service pas commandé). */
    public function isEditable(): bool
    {
        return in_array($this, [self::InStock, self::OnOrder, self::ToDo], true);
    }

    /** Les lignes annulées, retournées ou remboursées ne comptent plus dans le solde de la commande. */
    public function isBillable(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Returned, self::Refunded], true);
    }

    /** Lignes dont la partie réservée au client (en stock ou reçue) peut lui être remise. */
    public function isPickable(): bool
    {
        return in_array($this, [self::InStock, self::OnOrder, self::Ordered, self::Received], true);
    }

    /** Lignes remises au client (ramassées, livrées, expédiées ou service complété) : elles doivent être payées à 100 %. */
    public function isHandedOver(): bool
    {
        return in_array($this, [self::PickedUp, self::Delivered, self::Shipped, self::Completed], true);
    }
}
