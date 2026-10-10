<?php

namespace App\Enums;

enum DefectiveResolution: string
{
    case PartOrder = 'part_order';
    case Replacement = 'replacement';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::PartOrder => __('Pièce de remplacement commandée'),
            self::Replacement => __('Produit remplacé'),
            self::Refund => __('Remboursé sans frais'),
        };
    }

    /** Le produit défectueux a été repris au client (il est en inventaire défectueux). */
    public function isTakenBack(): bool
    {
        return $this !== self::PartOrder;
    }
}
