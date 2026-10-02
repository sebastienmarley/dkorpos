<?php

namespace App\Enums;

enum SupplierType: string
{
    case Product = 'product';
    case Service = 'service';
    case Shipping = 'shipping';

    public function label(): string
    {
        return match ($this) {
            self::Product => __('Produit'),
            self::Service => __('Service'),
            self::Shipping => __('Expédition'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Product => 'green',
            self::Service => 'blue',
            self::Shipping => 'amber',
        };
    }
}
