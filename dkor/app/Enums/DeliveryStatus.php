<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Scheduled = 'scheduled';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Planifiée'),
            self::InTransit => __('En route'),
            self::Delivered => __('Livrée'),
            self::Cancelled => __('Annulée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'sky',
            self::InTransit => 'blue',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    /** Une livraison active réserve ses articles et occupe une place dans sa demi-journée. */
    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::InTransit], true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Scheduled->value, self::InTransit->value];
    }
}
