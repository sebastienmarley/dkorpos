<?php

namespace App\Enums;

enum CustomerOrderStatus: string
{
    case New = 'new';
    case Pending = 'pending';
    case InDelivery = 'in_delivery';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** Statuts de fin : la commande ne doit plus rien et n'a plus d'article à remettre. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Delivered, self::PickedUp, self::Cancelled, self::Refunded], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => __('Nouvelle'),
            self::Pending => __('En attente'),
            self::InDelivery => __('En livraison'),
            self::Delivered => __('Livrée'),
            self::PickedUp => __('Ramassée'),
            self::Cancelled => __('Annulée'),
            self::Refunded => __('Remboursée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'zinc',
            self::Pending => 'yellow',
            self::InDelivery => 'blue',
            self::Delivered => 'green',
            self::PickedUp => 'green',
            self::Cancelled => 'red',
            self::Refunded => 'purple',
        };
    }
}
