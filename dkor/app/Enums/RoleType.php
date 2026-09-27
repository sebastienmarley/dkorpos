<?php

namespace App\Enums;

enum RoleType: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Design = 'design';
    case Delivery = 'delivery';
    case Kitchen = 'kitchen';
    case Warehouse = 'warehouse';
    case Salesman = 'salesman';
    case Accounting = 'accounting';
    case Manager = 'manager';
    case ThirdKey = 'thirdkey';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrateur'),
            self::Owner => __('Propriétaire'),
            self::Design => __('Designer'),
            self::Delivery => __('Livreur'),
            self::Kitchen => __('Cuisiniste'),
            self::Warehouse => __('Commis entrepôt'),
            self::Salesman => __('Vendeur'),
            self::Accounting => __('Comptabilité'),
            self::Manager => __('Directeur'),
            self::ThirdKey => __('Troisième clé'),
        };
    }
}
