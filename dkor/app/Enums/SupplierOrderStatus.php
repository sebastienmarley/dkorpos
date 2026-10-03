<?php

namespace App\Enums;

enum SupplierOrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(?SupplierType $type = null): string
    {
        return match ($this) {
            self::Draft => __('Brouillon'),
            self::Pending => __('En attente'),
            self::Sent => __('Envoyée'),
            self::PartiallyReceived => __('Partiellement reçue'),
            self::Received => $type === SupplierType::Service ? __('Complétée') : __('Reçue'),
            self::Invoiced => __('Facturée'),
            self::Cancelled => __('Annulée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Pending => 'yellow',
            self::Sent => 'blue',
            self::PartiallyReceived => 'amber',
            self::Received => 'green',
            self::Invoiced => 'purple',
            self::Cancelled => 'red',
        };
    }

    /** Une commande est modifiable (lignes, notes) tant qu'elle n'est pas envoyée au fournisseur. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Pending], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyReceived], true);
    }
}
