<?php

namespace App\Console\Commands;

use App\Actions\PurgeDeliverySignatures as PurgeDeliverySignaturesAction;
use Illuminate\Console\Command;

class PurgeDeliverySignatures extends Command
{
    protected $signature = 'deliveries:purge-signatures';

    protected $description = 'Supprime les images de signature des livraisons conservées depuis plus que la durée prévue';

    public function handle(PurgeDeliverySignaturesAction $purge): int
    {
        $this->info('Signatures supprimées : '.$purge->handle());

        return self::SUCCESS;
    }
}
