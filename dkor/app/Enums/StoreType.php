<?php

namespace App\Enums;

enum StoreType: string
{
    case Physical = 'physical';
    case Virtual = 'virtual';

    public function label(): string
    {
        return match ($this) {
            self::Physical => __('Physique'),
            self::Virtual => __('Virtuel'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Physical => 'green',
            self::Virtual => 'blue',
        };
    }
}
