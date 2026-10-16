<?php

namespace App\Enums;

/** Pourquoi un rendez-vous n'est pas couvert par un quart publié de son employé. */
enum AppointmentCoverage: string
{
    case NoShift = 'no_shift';
    case OutsideShift = 'outside_shift';
    case Unpublished = 'unpublished';

    public function label(): string
    {
        return match ($this) {
            self::NoShift => __('Aucun quart'),
            self::OutsideShift => __('Hors du quart'),
            self::Unpublished => __('Quart non publié'),
        };
    }

    /** Le rendez-vous resterait découvert même après la publication de la semaine. */
    public function blocksPublication(): bool
    {
        return $this !== self::Unpublished;
    }
}
