<?php

namespace App\Enums;

/**
 * Sortes d'avis envoyés à un client. Un avis automatique respecte le refus du client ; un envoi manuel, non.
 */
enum CustomerNoticeType: string
{
    case OrderSummary = 'order_summary';

    public function label(): string
    {
        return match ($this) {
            self::OrderSummary => __('Commande envoyée'),
        };
    }

    public function isAutomatic(): bool
    {
        return match ($this) {
            self::OrderSummary => false,
        };
    }
}
