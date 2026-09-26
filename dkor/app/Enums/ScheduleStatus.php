<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Non publiée'),
            self::Published => __('Publiée'),
            self::Closed => __('Fermée'),
            self::Paid => __('Payée'),
        };
    }

    /** Les statuts modifiables depuis le module horaire. */
    public static function editableValues(): array
    {
        return [self::Draft, self::Published];
    }
}
