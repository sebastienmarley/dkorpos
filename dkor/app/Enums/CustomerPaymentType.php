<?php

namespace App\Enums;

enum CustomerPaymentType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case CreditTransfer = 'credit_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Payment => __('Paiement'),
            self::Refund => __('Remboursement'),
            self::CreditTransfer => __('Crédit porté au compte du client'),
        };
    }
}
