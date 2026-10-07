<?php

namespace App\Enums;

enum CustomerOrderLineStatus: string
{
    case InStock = 'in_stock';
    case OnOrder = 'on_order';
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
            self::Received => 'lime',
            self::InDelivery, self::Shipped => 'blue',
            self::Delivered, self::PickedUp => 'green',
            self::Returned => 'orange',
            self::Refunded => 'purple',
            self::CancellationRequested => 'amber',
            self::Cancelled => 'red',
        };
    }
}
