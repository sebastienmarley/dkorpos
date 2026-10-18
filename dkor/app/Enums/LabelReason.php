<?php

namespace App\Enums;

/**
 * Raison pour laquelle une étiquette de prix est en attente d'impression.
 */
enum LabelReason: string
{
    case Manual = 'manual';
    case PriceChange = 'price_change';
    case NewDemo = 'new_demo';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Demande manuelle'),
            self::PriceChange => __('Changement de prix'),
            self::NewDemo => __('Nouveau démo'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'zinc',
            self::PriceChange => 'amber',
            self::NewDemo => 'purple',
        };
    }

    /** Raisons gérées automatiquement selon le prix affiché des démonstrateurs. */
    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }

    /** @return list<self> */
    public static function automatic(): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason): bool => $reason->isAutomatic()));
    }
}
