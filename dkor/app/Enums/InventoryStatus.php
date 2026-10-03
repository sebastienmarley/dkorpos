<?php

namespace App\Enums;

/**
 * États exclusifs d'une unité d'inventaire; chacun correspond à une colonne de quantité d'inventory_stocks.
 */
enum InventoryStatus: string
{
    case InStock = 'in_stock';
    case InDemo = 'in_demo';
    case OnOrder = 'on_order';
    case CustomerOrder = 'customer_order';
    case ReservedCustomer = 'reserved_customer';
    case InDelivery = 'in_delivery';
    case DefectiveStock = 'defective_stock';
    case DefectiveShipped = 'defective_shipped';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('En stock'),
            self::InDemo => __('En démo'),
            self::OnOrder => __('En commande'),
            self::CustomerOrder => __('En commande client'),
            self::ReservedCustomer => __('Réservé client'),
            self::InDelivery => __('En livraison'),
            self::DefectiveStock => __('Défectueux (stock)'),
            self::DefectiveShipped => __('Défectueux (expédié)'),
            self::Lost => __('Perdu'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'green',
            self::InDemo => 'purple',
            self::OnOrder => 'blue',
            self::CustomerOrder => 'sky',
            self::ReservedCustomer => 'amber',
            self::InDelivery => 'indigo',
            self::DefectiveStock, self::DefectiveShipped => 'orange',
            self::Lost => 'red',
        };
    }

    /** Colonne de quantité d'inventory_stocks pour cet état. */
    public function column(): string
    {
        return match ($this) {
            self::InStock => 'quantity_in_stock',
            self::InDemo => 'quantity_in_demo',
            self::OnOrder => 'quantity_on_order',
            self::CustomerOrder => 'quantity_customer_order',
            self::ReservedCustomer => 'quantity_reserved',
            self::InDelivery => 'quantity_in_delivery',
            self::DefectiveStock => 'quantity_defective_stock',
            self::DefectiveShipped => 'quantity_defective_shipped',
            self::Lost => 'quantity_lost',
        };
    }

    /** Les quantités « en commande » suivent les commandes fournisseurs: pas de transfert manuel. */
    public function isManuallyMovable(): bool
    {
        return $this !== self::OnOrder;
    }
}
