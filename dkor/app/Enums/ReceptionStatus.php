<?php

namespace App\Enums;

enum ReceptionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('En cours'),
            self::Completed => __('Terminée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InProgress => 'amber',
            self::Completed => 'green',
        };
    }
}
