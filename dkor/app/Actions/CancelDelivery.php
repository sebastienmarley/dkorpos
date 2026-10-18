<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Models\CustomerOrderDelivery;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Annule une livraison planifiée ou une expédition à faire : ses articles redeviennent disponibles (ramassage ou autre
 * livraison) et sa place dans la demi-journée se libère. Les livreurs (ou les responsables des expéditions) sont avisés.
 */
class CancelDelivery
{
    public function __construct(private NotifyDeliveryTeam $notifyTeam) {}

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

        $title = $delivery->method === DeliveryMethod::Shipping
            ? __('Expédition annulée')
            : __('Livraison annulée : :slot', ['slot' => $delivery->slotLabel()]);

        $this->notifyTeam->handle($delivery, $title, $reason);
    }
}
