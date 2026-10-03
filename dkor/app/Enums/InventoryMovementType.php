<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OrderPlaced = 'order_placed';
    case OrderLineAdded = 'order_line_added';
    case OrderLineChanged = 'order_line_changed';
    case OrderLineCancelled = 'order_line_cancelled';
    case OrderCancelled = 'order_cancelled';
    case Substitution = 'substitution';
    case Receipt = 'receipt';
    case ReceiptReversal = 'receipt_reversal';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => __('Commande envoyée'),
            self::OrderLineAdded => __('Ligne ajoutée'),
            self::OrderLineChanged => __('Quantité de ligne modifiée'),
            self::OrderLineCancelled => __('Ligne annulée'),
            self::OrderCancelled => __('Commande annulée'),
            self::Substitution => __('Substitution'),
            self::Receipt => __('Réception'),
            self::ReceiptReversal => __('Renversement de réception'),
            self::Transfer => __('Transfert manuel'),
        };
    }
}
