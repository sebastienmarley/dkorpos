<?php

namespace App\Actions;

use App\Models\CustomerOrderDelivery;
use DomainException;

/**
 * Envoie une expédition par son transporteur (issue #95). Ce qui n'est pas encore en main reste une expédition à
 * faire : l'entrepôt sera avisé quand ce reste sera en main.
 */
class ShipShipment
{
    public function __construct(private NotifyShipmentReady $notifyShipmentReady) {}

    /**
     * @param  array<int, int>  $quantities  quantité envoyée par ligne d'expédition
     * @return CustomerOrderDelivery|null l'expédition qui reste à faire, s'il y a lieu
     *
     * @throws DomainException
     */
    public function handle(CustomerOrderDelivery $shipment, array $quantities, string $trackingNumber): ?CustomerOrderDelivery
    {
        $remainder = $shipment->order()->firstOrFail()->shipDelivery($shipment, $quantities, $trackingNumber);

        if ($remainder !== null) {
            $this->notifyShipmentReady->handle($remainder);
        }

        return $remainder;
    }
}
