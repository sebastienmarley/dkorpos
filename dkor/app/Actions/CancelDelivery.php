<?php

namespace App\Actions;

use App\Enums\DeliveryStatus;
use App\Models\CustomerOrderDelivery;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Annule une livraison planifiée : ses articles redeviennent disponibles (ramassage ou autre livraison) et sa place
 * dans la demi-journée se libère. Les livreurs sont avisés.
 */
class CancelDelivery
{
    public function __construct(private NotifyDeliveryDrivers $notifyDrivers) {}

    /**
     * @throws DomainException si la livraison n'est plus planifiée (en route, livrée ou déjà annulée).
     */
    public function handle(CustomerOrderDelivery $delivery, ?string $reason = null): void
    {
        DB::transaction(function () use ($delivery, $reason): void {
            $locked = CustomerOrderDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DeliveryStatus::Scheduled) {
                throw new DomainException(__('Seule une livraison planifiée peut être annulée.'));
            }

            $delivery->update([
                'status' => DeliveryStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => filled($reason) ? $reason : null,
            ]);
        });

        $this->notifyDrivers->handle($delivery, __('Livraison annulée : :slot', ['slot' => $delivery->slotLabel()]), $reason);
    }
}
