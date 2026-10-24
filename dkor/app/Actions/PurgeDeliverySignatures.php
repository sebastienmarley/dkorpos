<?php

namespace App\Actions;

use App\Models\CustomerOrderDelivery;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime les images de signature conservées depuis plus longtemps que la durée prévue
 * (config `deliveries.signature_retention_days`). La livraison garde le nom du signataire, la date et ses articles.
 */
class PurgeDeliverySignatures
{
    /** @return int le nombre de signatures supprimées */
    public function handle(): int
    {
        $disk = Storage::disk(config('deliveries.signature_disk'));
        $count = 0;

        CustomerOrderDelivery::query()
            ->whereNotNull('signature_path')
            ->where('signed_at', '<', now()->subDays((int) config('deliveries.signature_retention_days')))
            ->each(function (CustomerOrderDelivery $delivery) use ($disk, &$count): void {
                $disk->delete((string) $delivery->signature_path);
                $delivery->update(['signature_path' => null]);
                $count++;
            });

        return $count;
    }
}
