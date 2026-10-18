<?php

namespace App\Enums;

enum LedgerAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('Actif'),
            self::Liability => __('Passif'),
            self::Equity => __('Capitaux propres'),
            self::Revenue => __('Produit'),
            self::Expense => __('Charge'),
        };
    }
}
