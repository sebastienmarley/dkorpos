<?php

namespace App\Enums;

enum DefectiveResolution: string
{
    case PartOrder = 'part_order';
    case Replacement = 'replacement';
    case Refund = 'refund';
    case DamagedOnArrival = 'damaged_on_arrival';

    public function label(): string
    {
        return match ($this) {
            self::PartOrder => __('Pièce de remplacement commandée'),
            self::Replacement => __('Produit remplacé'),
            self::Refund => __('Remboursé sans frais'),
            self::DamagedOnArrival => __('Endommagé à la réception (à traiter)'),
        };
    }

    /** Le produit défectueux est en inventaire défectueux (repris au client ou reçu endommagé). */
    public function isTakenBack(): bool
    {
        return $this !== self::PartOrder;
    }
}
