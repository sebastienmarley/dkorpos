<?php

namespace App\Enums;

/**
 * Suivi d'un dossier défectueux (la gestion des défectueux viendra plus tard).
 */
enum DefectiveStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Ouvert'),
            self::Closed => __('Fermé'),
        };
    }
}
