<?php

namespace App\Enums;

/** Façon de remettre une commande au client sans qu'il vienne la ramasser. */
enum DeliveryMethod: string
{
    /** Par nos livreurs et nos camions, à une date et une demi-journée planifiées. */
    case Internal = 'internal';

    /** Par un fournisseur d'expédition (issue #86). */
    case Shipping = 'shipping';

    public function label(): string
    {
        return match ($this) {
            self::Internal => __('Livraison'),
            self::Shipping => __('Expédition'),
        };
    }
}
