<?php

namespace App\Actions;

use App\Enums\StoreTransferStatus;
use App\Models\InventoryCount;
use App\Models\StoreTransfer;
use App\Models\User;
use DomainException;

/**
 * Règles communes aux étapes d'un transfert entre magasins (issue #103).
 */
class StoreTransferGuard
{
    /** L'usager agit pour son magasin; un usager sans magasin (bureau chef) agit pour tous. */
    public static function actingFor(User $user, int $storeId, string $message): void
    {
        if ($user->store_id !== null && $user->store_id !== $storeId) {
            throw new DomainException($message);
        }
    }

    public static function status(StoreTransfer $transfer, StoreTransferStatus $expected, string $message): void
    {
        if ($transfer->status !== $expected) {
            throw new DomainException($message);
        }
    }

    /** Aucun transfert ne bouge pendant le gel du stock de l'un des deux magasins. */
    public static function notFrozen(StoreTransfer $transfer): void
    {
        foreach ([$transfer->from_store_id, $transfer->to_store_id] as $storeId) {
            if (InventoryCount::stockIsFrozen($storeId)) {
                throw new DomainException(__('Le stock d\'un des magasins est gelé pour une prise d\'inventaire : le transfert attend sa finalisation.'));
            }
        }
    }
}
