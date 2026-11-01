<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Demande l'expédition d'articles d'une commande par un fournisseur d'expédition (issue #86). L'expédition appartient
 * à l'entrepôt d'expédition du magasin de la commande, qui règle plus tard le transporteur et l'envoi dans son menu
 * d'expédition. Sans entrepôt d'expédition, le magasin n'offre pas l'expédition.
 *
 * Les articles n'ont pas à être reçus, mais ils doivent être payés à 100 %, comme les frais d'expédition (dépôt sur
 * le reste de la commande) : on ne peut pas encaisser chez le client. Quand tous les articles sont en main, les
 * responsables des expéditions sont avisés.
 */
class RequestShipment
{
    public function __construct(private NotifyShipmentReady $notifyShipmentReady) {}

    /**
     * @param  array<int, int>  $quantities  quantité à expédier par ligne
     *
     * @throws DomainException si le magasin n'offre pas l'expédition, si l'adresse est incomplète, si une quantité
     *                         n'est plus à remettre ou si le paiement exigé n'est pas encaissé.
     */
    public function handle(CustomerOrder $order, array $quantities, ?string $notes = null): CustomerOrderDelivery
    {
        $warehouseId = $order->store?->shipping_warehouse_id;

        if ($warehouseId === null) {
            throw new DomainException(__('Ce magasin n\'a pas d\'entrepôt d\'expédition : l\'expédition n\'est pas offerte.'));
        }

        $shipment = DB::transaction(function () use ($order, $quantities, $notes, $warehouseId): CustomerOrderDelivery {
            CustomerOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $quantities = $order->guardDeliverableQuantities($quantities);
            $required = $order->amountRequiredForShipment($quantities);

            if ($required->isPositive()) {
                throw new DomainException(__('Réglez la facture avant de demander l\'expédition : :amount $ à payer (les articles expédiés doivent être payés à 100 %).', ['amount' => $required->toDecimal()]));
            }

            $shipment = $order->deliveries()->create([
                ...$order->verifiedDeliveryAddress(),
                'store_id' => $warehouseId,
                'method' => DeliveryMethod::Shipping,
                'status' => DeliveryStatus::Scheduled,
                'notes' => filled($notes) ? $notes : null,
                'requested_by' => auth()->id(),
            ]);

            foreach ($quantities as $lineId => $quantity) {
                $shipment->lines()->create(['customer_order_line_id' => $lineId, 'quantity' => $quantity]);
            }

            return $shipment;
        });

        $this->notifyShipmentReady->handle($shipment);

        return $shipment;
    }
}
