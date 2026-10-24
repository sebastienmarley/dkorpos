<?php

namespace App\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\StoreTransferStatus;
use App\Models\InventoryMovement;
use App\Models\StoreTransfer;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le magasin qui reçoit confirme la réception d'un transfert en transit (issue #103) : les unités FIFO passent dans son
 * stock à leur coût, « En stock » (ou « Réservé client », la ligne passant « Transfert reçu »). Un écart (moins reçu
 * qu'envoyé) devient « Perdu » chez le magasin qui reçoit; pour un client, il est commandé au fournisseur.
 */
class ReceiveStoreTransfer
{
    public function __construct(private NotifyStoreTransfer $notify) {}

    /**
     * @throws DomainException
     */
    public function handle(User $user, StoreTransfer $transfer, int $received): void
    {
        DB::transaction(function () use ($user, $transfer, $received): void {
            $transfer = StoreTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            StoreTransferGuard::actingFor($user, $transfer->to_store_id, __('Seul le magasin qui reçoit confirme la réception d\'un transfert.'));
            StoreTransferGuard::status($transfer, StoreTransferStatus::InTransit, __('Seul un transfert en transit peut être reçu.'));

            if ($received < 0 || $received > $transfer->quantity) {
                throw new DomainException(__('La quantité reçue doit être entre 0 et la quantité envoyée (:count).', ['count' => $transfer->quantity]));
            }

            InventoryMovement::moveBetweenStores(
                $transfer->product_id,
                $transfer->from_store_id,
                $transfer->transitStatus(),
                $transfer->to_store_id,
                [$transfer->arrivalStatus()->value => $received, InventoryStatus::Lost->value => $transfer->quantity - $received],
                InventoryMovementType::TransferSent,
                InventoryMovementType::TransferReceived,
                $transfer,
                $user->id,
            );

            $transfer->update(['status' => StoreTransferStatus::Received, 'quantity_received' => $received, 'received_by' => $user->id, 'received_at' => now()]);

            if ($transfer->line !== null) {
                $transfer->line->order->receiveTransferLine($transfer->line, $received);
            }
        });

        $transfer->refresh();

        if ($transfer->isForCustomer()) {
            $this->notify->toSalespeople($transfer, $transfer->quantity_received < $transfer->quantity
                ? __('Transfert reçu avec un écart : le manquant est commandé au fournisseur')
                : __('Transfert reçu : l\'article du client est prêt'));
        }

        $this->notify->toRequester($transfer, __('Transfert reçu de :store', ['store' => $transfer->fromStore->name]));
    }
}
