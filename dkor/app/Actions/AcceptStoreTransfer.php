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
 * Le magasin qui expédie accepte un transfert demandé (issue #103) : la marchandise passe « En transit » (« Client en
 * transit » pour un client), toujours dans son stock, et le magasin qui reçoit est avisé.
 */
class AcceptStoreTransfer
{
    public function __construct(private NotifyStoreTransfer $notify) {}

    /**
     * @throws DomainException
     */
    public function handle(User $user, StoreTransfer $transfer): void
    {
        DB::transaction(function () use ($user, $transfer): void {
            $transfer = StoreTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            StoreTransferGuard::actingFor($user, $transfer->from_store_id, __('Seul le magasin qui expédie accepte un transfert.'));
            StoreTransferGuard::status($transfer, StoreTransferStatus::Requested, __('Seul un transfert demandé peut être accepté.'));
            StoreTransferGuard::notFrozen($transfer);

            InventoryMovement::record($transfer->product_id, $transfer->from_store_id, $transfer->requestedStatus(), $transfer->transitStatus(), $transfer->quantity, InventoryMovementType::TransferAccepted, $transfer, userId: $user->id);

            $transfer->update(['status' => StoreTransferStatus::InTransit, 'accepted_by' => $user->id, 'accepted_at' => now()]);
        });

        $transfer->refresh();
        $this->notify->toStore($transfer, $transfer->to_store_id, 'transfers.receive', __('Transfert en route de :store', ['store' => $transfer->fromStore->name]));
        $this->notify->toRequester($transfer, __('Transfert accepté par :store', ['store' => $transfer->fromStore->name]));
    }
}
