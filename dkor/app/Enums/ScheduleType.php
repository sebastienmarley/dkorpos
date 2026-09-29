<?php

namespace App\Enums;

enum ScheduleType: string
{
    case Work = 'work';
    case Sick = 'sick';
    case Absent = 'absent';
    case Vacation = 'vacation';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Work => __('Travail'),
            self::Sick => __('Maladie'),
            self::Absent => __('Absent'),
            self::Vacation => __('Vacances'),
            self::Holiday => __('Férié'),
        };
    }

    /** Une absence n'a pas d'heures et bloque la prise de rendez-vous ce jour-là. */
    public function isAbsence(): bool
    {
        return $this !== self::Work;
    }

    /** Couleur du badge Flux. */
    public function color(): string
    {
        return match ($this) {
            self::Work => 'blue',
            self::Sick => 'red',
            self::Absent => 'amber',
            self::Vacation => 'sky',
            self::Holiday => 'violet',
        };
    }
}
