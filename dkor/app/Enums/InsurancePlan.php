<?php

namespace App\Enums;

enum InsurancePlan: string
{
    case Family = 'family';
    case Individual = 'individual';
    case SingleParent = 'single_parent';

    public function label(): string
    {
        return match ($this) {
            self::Family => __('Familiale'),
            self::Individual => __('Individuelle'),
            self::SingleParent => __('Monoparentale'),
        };
    }
}
