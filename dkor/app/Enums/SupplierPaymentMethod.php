<?php

namespace App\Enums;

/** Façon dont un fournisseur est payé (#132). */
enum SupplierPaymentMethod: string
{
    case Cheque = 'cheque';
    case Transfer = 'transfer';
    case Card = 'card';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Cheque => __('Chèque'),
            self::Transfer => __('Virement'),
            self::Card => __('Carte de crédit'),
            self::Debit => __('Prélèvement'),
        };
    }
}
