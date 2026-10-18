<?php

namespace App\Enums;

/**
 * Raison pour laquelle une étiquette de prix est en attente d'impression.
 */
enum LabelReason: string
{
    case Manual = 'manual';
    case PriceChange = 'price_change';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Demande manuelle'),
            self::PriceChange => __('Changement de prix'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'zinc',
            self::PriceChange => 'amber',
        };
    }
}
