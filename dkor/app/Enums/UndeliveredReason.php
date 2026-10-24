<?php

namespace App\Enums;

/** Raison d'une livraison « Non livrée » : rien n'a été remis au client. */
enum UndeliveredReason: string
{
    case CustomerAbsent = 'customer_absent';
    case AllRefused = 'all_refused';

    public function label(): string
    {
        return match ($this) {
            self::CustomerAbsent => __('Client absent'),
            self::AllRefused => __('Tout refusé'),
        };
    }
}
