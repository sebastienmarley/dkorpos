<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Models\CustomerOrderDelivery;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Fixe l'ordre de la route d'une demi-journée (issue #94) : les livraisons actives du magasin reçoivent leur rang,
 * le même pour tous les livreurs du magasin.
 */
class ReorderDeliveryRoute
{
    /**
     * @param  list<int>  $orderedIds  livraisons dans l'ordre de la route
     *
     * @throws DomainException si la liste ne correspond pas aux livraisons de la demi-journée.
     */
    public function handle(int $storeId, string $date, DeliveryPeriod $period, array $orderedIds): void
    {
        DB::transaction(function () use ($storeId, $date, $period, $orderedIds): void {
            $deliveries = CustomerOrderDelivery::query()
                ->active()
                ->where('method', DeliveryMethod::Internal)
                ->where('store_id', $storeId)
                ->whereDate('scheduled_date', $date)
                ->where('period', $period)
                ->lockForUpdate()
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $requested = collect($orderedIds)->sort()->values()->all();

            if ($requested !== $deliveries) {
                throw new DomainException(__('La route a changé pendant que vous la modifiiez : rechargez la page.'));
            }

            foreach ($orderedIds as $position => $id) {
                CustomerOrderDelivery::query()->whereKey($id)->update(['route_position' => $position + 1]);
            }
        });
    }
}
