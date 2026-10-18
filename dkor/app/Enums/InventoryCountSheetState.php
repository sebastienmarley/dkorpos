<?php

namespace App\Enums;

enum InventoryCountSheetState: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => __('À faire'),
            self::InProgress => __('En cours'),
            self::Done => __('Terminée'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'zinc',
            self::InProgress => 'amber',
            self::Done => 'green',
        };
    }
}
