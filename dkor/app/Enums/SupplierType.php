<?php

namespace App\Enums;

enum SupplierType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => __('Produit'),
            self::Service => __('Service'),
        };
    }
}
