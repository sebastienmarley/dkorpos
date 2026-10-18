<?php

namespace App\Actions;

use App\Models\LabelRequest;
use App\Models\User;
use App\Notifications\LabelsToPrint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Avise les responsables de l'impression (permission labels.print) des étiquettes en attente. Le rappel précédent
 * non lu est remplacé pour ne pas empiler un avis par jour; quand la file est vide, les rappels sont marqués lus.
 */
class NotifyLabelsToPrint
{
    public function handle(): void
    {
        /** @var array<string, int> $quantitiesByReason */
        $quantitiesByReason = LabelRequest::query()
            ->pending()
            ->selectRaw('reason, sum(quantity) as quantity')
            ->groupBy('reason')
            ->pluck('quantity', 'reason')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();

        if ($quantitiesByReason === []) {
            $this->clear();

            return;
        }

        $this->unreadReminders()->delete();

        User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can('labels.print'))
            ->each(fn (User $user) => $user->notify(new LabelsToPrint($quantitiesByReason)));
    }

    /**
     * Marque les rappels comme lus si plus aucune étiquette n'attend.
     */
    public function clearIfQueueEmpty(): void
    {
        if (! LabelRequest::query()->pending()->exists()) {
            $this->clear();
        }
    }

    private function clear(): void
    {
        $this->unreadReminders()->update(['read_at' => now()]);
    }

    /** @return Builder<DatabaseNotification> */
    private function unreadReminders(): Builder
    {
        return DatabaseNotification::query()->where('type', LabelsToPrint::Type)->whereNull('read_at');
    }
}
