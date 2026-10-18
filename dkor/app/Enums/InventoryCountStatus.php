<?php

namespace App\Enums;

enum InventoryCountStatus: string
{
    case InProgress = 'in_progress';
    case Finalized = 'finalized';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('En cours'),
            self::Finalized => __('Finalisée'),
            self::Abandoned => __('Abandonnée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InProgress => 'amber',
            self::Finalized => 'green',
            self::Abandoned => 'zinc',
        };
    }
}
