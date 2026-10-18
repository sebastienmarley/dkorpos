<?php

namespace App\Enums;

enum InventoryCountPass: string
{
    case A = 'a';
    case B = 'b';
    case Recount = 'recount';

    public function label(): string
    {
        return match ($this) {
            self::A => __('Compte A'),
            self::B => __('Compte B'),
            self::Recount => __('Recompte'),
        };
    }
}
