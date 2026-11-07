<?php

namespace App\Actions;

use App\Enums\CustomerNoticeType;
use App\Models\CustomerOrderDelivery;
use Illuminate\Support\Facades\DB;

/**
 * Avise le client d'un changement à sa livraison ou à son expédition (issue #136), une seule fois par événement et
 * seulement une fois la transaction confirmée : un changement annulé n'envoie rien.
 */
class NotifyCustomerOfDelivery
{
    public function __construct(private NotifyCustomer $notifyCustomer) {}

    public function handle(CustomerOrderDelivery $delivery, CustomerNoticeType $type): void
    {
        DB::afterCommit(function () use ($delivery, $type): void {
            $order = $delivery->order()->with('customer')->firstOrFail();

            $this->notifyCustomer->handle(
                $order->customer,
                $type,
                $order,
                eventKey: $type->value.':'.$delivery->id,
                createdBy: auth()->id(),
                subject: $delivery,
            );
        });
    }
}
