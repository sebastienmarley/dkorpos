<?php

namespace App\Enums;

/** Étape d'un transfert entre magasins (issue #103). */
enum StoreTransferStatus: string
{
    case Requested = 'requested';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Demandé'),
            self::InTransit => __('En transit'),
            self::Received => __('Reçu'),
            self::Refused => __('Refusé'),
            self::Cancelled => __('Annulé'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'cyan',
            self::InTransit => 'teal',
            self::Received => 'green',
            self::Refused => 'red',
            self::Cancelled => 'zinc',
        };
    }

    /** Le transfert retient encore la marchandise dans le magasin qui expédie. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::InTransit], true);
    }
}
