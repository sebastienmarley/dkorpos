<?php

namespace App\Actions;

use App\Enums\CustomerNoticeType;
use App\Models\CustomerOrder;
use Illuminate\Support\Facades\DB;

/**
 * Avise le client, une seule fois par commande, que sa commande est prête à ramasser (issue #137) : appelée à la
 * réception d'un article commandé pour lui, une fois la transaction confirmée.
 */
class NotifyCustomerPickupReady
{
    public function __construct(private NotifyCustomer $notifyCustomer) {}

    public function handle(CustomerOrder $order): void
    {
        DB::afterCommit(function () use ($order): void {
            $order = $order->fresh(['customer']);

            if (! $order->isReadyForPickup()) {
                return;
            }

            $this->notifyCustomer->handle(
                $order->customer,
                CustomerNoticeType::PickupReady,
                $order,
                eventKey: CustomerNoticeType::PickupReady->value.':'.$order->id,
                createdBy: auth()->id(),
            );
        });
    }
}
