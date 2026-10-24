<?php

namespace App\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\StoreTransferStatus;
use App\Models\CustomerOrderLine;
use App\Models\InventoryCount;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Demande un transfert à un autre magasin (issue #103). C'est toujours le magasin qui reçoit qui le demande : pour son
 * stock (depuis « En stock » seulement) ou pour une ligne de commande client (depuis « En stock », ou « En démo » pour
 * la dernière unité). La quantité passe « En transfert » (ou « Client en transfert ») chez le magasin qui expédie, qui
 * est avisé. Pour un client, la quantité est retirée de la commande au fournisseur.
 */
class RequestStoreTransfer
{
    public function __construct(private NotifyStoreTransfer $notify) {}

    /**
     * @throws DomainException
     */
    public function handle(User $user, Product $product, int $fromStoreId, int $quantity, ?CustomerOrderLine $line = null, InventoryStatus $source = InventoryStatus::InStock): StoreTransfer
    {
        $toStoreId = $line !== null ? $line->order()->firstOrFail()->stockStoreId() : Store::defaultIdFor($user);

        if ($toStoreId === null || ($user->store_id !== null && $user->store_id !== $toStoreId)) {
            throw new DomainException(__('Seul le magasin qui reçoit la marchandise demande un transfert.'));
        }

        if ($fromStoreId === $toStoreId) {
            throw new DomainException(__('Choisissez un autre magasin que celui qui reçoit.'));
        }

        $isForCustomer = $line instanceof CustomerOrderLine;

        if ($source !== InventoryStatus::InStock && ! ($isForCustomer && $source === InventoryStatus::InDemo)) {
            throw new DomainException($isForCustomer
                ? __('Un transfert pour un client part du stock ou d\'un démonstrateur.')
                : __('Un transfert de stock part de la marchandise « En stock » : un démonstrateur ne se transfère que pour un client.'));
        }

        if ($line instanceof CustomerOrderLine && $line->product_id !== $product->id) {
            throw new DomainException(__('Le produit ne correspond pas à la ligne de commande.'));
        }

        if ($quantity < 1) {
            throw new DomainException(__('La quantité à transférer doit être d\'au moins 1.'));
        }

        foreach ([$fromStoreId, $toStoreId] as $storeId) {
            if (InventoryCount::stockIsFrozen($storeId)) {
                throw new DomainException(__('Le stock d\'un des magasins est gelé pour une prise d\'inventaire : le transfert attend sa finalisation.'));
            }
        }

        $transfer = DB::transaction(function () use ($user, $product, $fromStoreId, $toStoreId, $quantity, $line, $source): StoreTransfer {
            $transferLine = $line instanceof CustomerOrderLine ? $line->order()->firstOrFail()->takeLineForTransfer($line, $quantity) : null;

            $transfer = StoreTransfer::create([
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'product_id' => $product->id,
                'customer_order_line_id' => $transferLine?->id,
                'quantity' => $quantity,
                'source_status' => $source,
                'status' => StoreTransferStatus::Requested,
                'requested_by' => $user->id,
                'requested_at' => now(),
            ]);

            InventoryMovement::record($product, $fromStoreId, $source, $transfer->requestedStatus(), $quantity, InventoryMovementType::TransferRequested, $transfer, userId: $user->id);

            return $transfer;
        });

        $this->notify->toStore($transfer, $fromStoreId, 'transfers.accept', __('Transfert demandé par :store', ['store' => $transfer->toStore->name]));

        return $transfer;
    }
}
