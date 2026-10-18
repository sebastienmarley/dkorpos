<?php

namespace App\Console\Commands;

use App\Actions\NotifyLabelsToPrint;
use App\Actions\QueuePriceChangeLabels as QueuePriceChangeLabelsAction;
use App\Models\LabelRequest;
use Illuminate\Console\Command;

class QueuePriceChangeLabels extends Command
{
    protected $signature = 'labels:queue-price-changes';

    protected $description = 'Met en file les étiquettes des démonstrateurs dont le prix affiché n\'est plus à jour et avise les responsables';

    public function handle(QueuePriceChangeLabelsAction $queuePriceChangeLabels, NotifyLabelsToPrint $notifyLabelsToPrint): int
    {
        $queuePriceChangeLabels->all();
        $notifyLabelsToPrint->handle();

        $this->info('Étiquettes en attente : '.LabelRequest::query()->pending()->sum('quantity'));

        return self::SUCCESS;
    }
}
