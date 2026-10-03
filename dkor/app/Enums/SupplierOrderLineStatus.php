<?php

namespace App\Enums;

enum SupplierOrderLineStatus: string
{
    case Active = 'active';
    case CancellationRequested = 'cancellation_requested';
    case Cancelled = 'cancelled';
    case Substituted = 'substituted';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::CancellationRequested => __('En demande d\'annulation'),
            self::Cancelled => __('Annulée'),
            self::Substituted => __('Substituée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::CancellationRequested => 'amber',
            self::Cancelled => 'red',
            self::Substituted => 'sky',
        };
    }

    /** Une ligne annulée ou substituée n'a plus rien à recevoir et ne compte plus dans le total. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Cancelled, self::Substituted], true);
    }
}
