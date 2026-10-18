<?php

namespace App\Enums;

use App\Support\AppointmentBooking;

/** Demi-journée de livraison. */
enum DeliveryPeriod: string
{
    case Am = 'am';
    case Pm = 'pm';

    /** Midi : fin de l'avant-midi et début de l'après-midi, en minutes depuis minuit. */
    public const int NOON_MINUTE = 12 * 60;

    public function label(): string
    {
        return match ($this) {
            self::Am => __('AM'),
            self::Pm => __('PM'),
        };
    }

    /** Début de la demi-journée, en minutes depuis minuit. */
    public function startMinute(): int
    {
        return match ($this) {
            self::Am => AppointmentBooking::DAY_START_MINUTE,
            self::Pm => self::NOON_MINUTE,
        };
    }

    /** Fin de la demi-journée, en minutes depuis minuit. */
    public function endMinute(): int
    {
        return match ($this) {
            self::Am => self::NOON_MINUTE,
            self::Pm => AppointmentBooking::DAY_END_MINUTE,
        };
    }
}
