<?php

namespace App\Enums;

enum CustomerOrderStatus: string
{
    case New = 'new';
    case Pending = 'pending';
    case InDelivery = 'in_delivery';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';

    public function label(): string
    {
        return match ($this) {
            self::New => __('Nouvelle'),
            self::Pending => __('En attente'),
            self::InDelivery => __('En livraison'),
            self::Delivered => __('Livrée'),
            self::PickedUp => __('Ramassée'),
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
        };
    }
}
