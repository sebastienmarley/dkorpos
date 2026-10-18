<?php

namespace App\Notifications;

use App\Enums\LabelReason;
use Illuminate\Notifications\Notification;

/**
 * Rappel des étiquettes de prix en attente d'impression, remplacé à chaque passe quotidienne.
 */
class LabelsToPrint extends Notification
{
    public const string Type = 'labels-to-print';

    /**
     * @param  array<string, int>  $quantitiesByReason  quantité en attente par valeur de LabelReason
     */
    public function __construct(public array $quantitiesByReason) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::Type;
    }

    /**
     * Forme commune des notifications affichées par la cloche.
     *
     * @return array{title: string, body: string, url: string, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        $total = array_sum($this->quantitiesByReason);

        $details = collect($this->quantitiesByReason)
            ->map(fn (int $quantity, string $reason): string => LabelReason::from($reason)->label().' : '.$quantity)
            ->implode(', ');

        return [
            'title' => trans_choice(':count étiquette à imprimer|:count étiquettes à imprimer', $total),
            'body' => $details,
            'url' => route('labels.index'),
            'icon' => 'printer',
        ];
    }
}
