<?php

namespace App\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\StoreTransferStatus;
use App\Models\InventoryMovement;
use App\Models\StoreTransfer;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ferme un transfert demandé, pas encore en transit (issue #103) : le magasin qui expédie le refuse (avec une raison)
 * ou le magasin qui reçoit l'annule. La marchandise revient dans son état d'origine chez le magasin qui expédie. Pour
 * un client, la ligne est commandée au fournisseur et les vendeurs sont avisés du délai.
 */
class CloseStoreTransfer
{
    public function __construct(private NotifyStoreTransfer $notify) {}

    /**
     * @param  bool  $isRefusal  vrai : refusé par le magasin qui expédie; faux : annulé par le magasin qui reçoit
     *
     * @throws DomainException
     */
    public function handle(User $user, StoreTransfer $transfer, bool $isRefusal, ?string $reason = null): void
    {
        if ($isRefusal && blank($reason)) {
            throw new DomainException(__('Indiquez la raison du refus.'));
        }

        DB::transaction(function () use ($user, $transfer, $isRefusal, $reason): void {
            $transfer = StoreTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            StoreTransferGuard::actingFor(
                $user,
                $isRefusal ? $transfer->from_store_id : $transfer->to_store_id,
                $isRefusal ? __('Seul le magasin qui expédie refuse un transfert.') : __('Seul le magasin qui a demandé le transfert l\'annule.'),
            );
            StoreTransferGuard::status($transfer, StoreTransferStatus::Requested, __('Seul un transfert demandé, pas encore en transit, peut être refusé ou annulé.'));
            StoreTransferGuard::notFrozen($transfer);

            InventoryMovement::record($transfer->product_id, $transfer->from_store_id, $transfer->requestedStatus(), $transfer->source_status, $transfer->quantity, InventoryMovementType::TransferReturned, $transfer, $reason, $user->id);

            $transfer->update([
                'status' => $isRefusal ? StoreTransferStatus::Refused : StoreTransferStatus::Cancelled,
                'refusal_reason' => filled($reason) ? trim($reason) : null,
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            if ($transfer->line !== null) {
                $transfer->line->order->returnLineFromTransfer($transfer->line);
            }
        });

        $transfer->refresh();

        if ($isRefusal) {
            $this->notify->toRequester($transfer, __('Transfert refusé par :store : :reason', ['store' => $transfer->fromStore->name, 'reason' => $transfer->refusal_reason]));
        } else {
            $this->notify->toStore($transfer, $transfer->from_store_id, 'transfers.accept', __('Transfert annulé par :store', ['store' => $transfer->toStore->name]));
        }

        if ($transfer->isForCustomer()) {
            $this->notify->toSalespeople($transfer, __('Transfert non fait : l\'article est commandé au fournisseur, prévenez le client du délai'));
        }
    }
}
