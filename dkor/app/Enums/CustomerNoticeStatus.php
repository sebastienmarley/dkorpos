<?php

namespace App\Enums;

enum CustomerNoticeStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case NoEmail = 'no_email';
    case OptedOut = 'opted_out';

    public function label(): string
    {
        return match ($this) {
            self::Sent => __('Envoyé'),
            self::Failed => __('Échoué'),
            self::NoEmail => __('Aucun courriel'),
            self::OptedOut => __('Refusé par le client'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sent => 'green',
            self::Failed => 'red',
            self::NoEmail => 'amber',
            self::OptedOut => 'zinc',
        };
    }
}
