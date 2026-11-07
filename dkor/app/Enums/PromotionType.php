<?php

namespace App\Enums;

/**
 * Sortes de promotions (issue #84) : un pourcentage de rabais, un montant fixe à l'achat d'un nombre d'articles, ou
 * « nous payons les taxes » (le total, taxes incluses, égale le prix avant taxes).
 */
enum PromotionType: string
{
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';
    case TaxesPaid = 'taxes_paid';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Pourcentage de rabais'),
            self::FixedAmount => __('Montant fixe à l\'achat de plusieurs articles'),
            self::TaxesPaid => __('Nous payons les taxes'),
        };
    }
}
