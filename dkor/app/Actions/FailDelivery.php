<?php

namespace App\Actions;

use App\Enums\UndeliveredReason;
use App\Models\CustomerOrderDelivery;
use DomainException;

/**
 * Ferme une livraison en route sans rien remettre (issue #93) : client absent, ou articles tous refusés. Les
 * articles retournent en stock client, la livraison passe « Non livrée » et les vendeurs sont avisés.
 */
class FailDelivery
{
    public function __construct(private NotifyDeliverySalespeople $notifySalespeople) {}

    /**
     * @param  array<int, string>  $refusalReasons  raison par ligne de livraison (tout refusé)
     *
     * @throws DomainException
     */
    public function handle(CustomerOrderDelivery $delivery, UndeliveredReason $reason, array $refusalReasons = []): void
    {
        $order = $delivery->order()->firstOrFail();
        $returned = $order->failDelivery($delivery, $reason, $refusalReasons);

        $this->notifySalespeople->handle(
            $delivery->refresh(),
            __('Livraison non livrée (:reason) — commande #:id', ['reason' => mb_strtolower($reason->label()), 'id' => $order->id]),
            $returned,
        );
    }
}
