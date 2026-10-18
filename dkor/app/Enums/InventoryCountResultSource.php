<?php

namespace App\Enums;

enum InventoryCountResultSource: string
{
    case Agreed = 'agreed';
    case Recount = 'recount';
    case Supervisor = 'supervisor';

    public function label(): string
    {
        return match ($this) {
            self::Agreed => __('A = B'),
            self::Recount => __('Recompte concordant'),
            self::Supervisor => __('Recompte retenu par le superviseur'),
        };
    }
}
