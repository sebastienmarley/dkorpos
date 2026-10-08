<?php

namespace App\Enums;

enum CustomerOrderLineStatus: string
{
    case InStock = 'in_stock';
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

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('En inventaire'),
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
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'zinc',
            self::OnOrder => 'yellow',
            self::Ordered => 'sky',
            self::Received => 'lime',
            self::InDelivery, self::Shipped => 'blue',
            self::Delivered, self::PickedUp => 'green',
            self::Returned => 'orange',
            self::Refunded => 'purple',
            self::CancellationRequested => 'amber',
            self::Cancelled => 'red',
        };
    }

    /** Une ligne peut être modifiée ou retirée tant que la marchandise n'est pas reçue ni sortie. */
    public function isEditable(): bool
    {
        return in_array($this, [self::InStock, self::OnOrder], true);
    }

    /** Les lignes annulées, retournées ou remboursées ne comptent plus dans le solde de la commande. */
    public function isBillable(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Returned, self::Refunded], true);
    }
}
