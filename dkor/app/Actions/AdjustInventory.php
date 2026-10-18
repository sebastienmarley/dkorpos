<?php

namespace App\Actions;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Ajuste l'inventaire d'un produit sans document d'achat ni de vente (issue #52, ADR 0011) : perte, vol, usage
 * magasin, correction de comptage ou stock initial.
 *
 * Dans une seule transaction : l'ajustement, son mouvement d'inventaire (qui sort les unités FIFO, ou en crée au coût
 * du produit, et en donne la valeur) et son écriture au grand livre, du même montant, contre le compte Stock. Une
 * valeur nulle (unités sans coût) ne passe pas d'écriture.
 */
class AdjustInventory
{
    /**
     * @param  bool  $isEntry  Vrai pour une entrée en inventaire, faux pour une sortie.
     *
     * @throws AuthorizationException sans la permission inventory.adjust.
     * @throws DomainException si le motif ne permet pas ce sens ou cet état, ou si la quantité est insuffisante.
     */
    public function handle(User $user, Product $product, InventoryAdjustmentReason $reason, InventoryStatus $status, bool $isEntry, int $quantity, string $note): InventoryAdjustment
    {
        Gate::forUser($user)->authorize('inventory.adjust');

        if ($isEntry ? ! $reason->allowsEntry() : ! $reason->allowsExit()) {
            throw new DomainException($isEntry
                ? __('Le motif « :reason » ne permet pas d\'entrée en inventaire.', ['reason' => $reason->label()])
                : __('Le motif « :reason » ne permet pas de sortie d\'inventaire.', ['reason' => $reason->label()]));
        }

        if (! in_array($status, $reason->statuses(), true)) {
            throw new DomainException(__('Le motif « :reason » ne s\'applique pas à l\'état « :status ».', ['reason' => $reason->label(), 'status' => $status->label()]));
        }

        if ($quantity < 1) {
            throw new DomainException(__('La quantité à ajuster doit être d\'au moins 1.'));
        }

        if (blank($note)) {
            throw new DomainException(__('Expliquez l\'ajustement dans la note.'));
        }

        return DB::transaction(function () use ($user, $product, $reason, $status, $isEntry, $quantity, $note): InventoryAdjustment {
            $adjustment = InventoryAdjustment::create([
                'product_id' => $product->id,
                'reason' => $reason,
                'status' => $status,
                'is_entry' => $isEntry,
                'quantity' => $quantity,
                'note' => trim($note),
                'user_id' => $user->id,
            ]);

            $movement = InventoryMovement::record(
                $product,
                $isEntry ? null : $status,
                $isEntry ? $status : null,
                $quantity,
                InventoryMovementType::Adjustment,
                $adjustment,
                $adjustment->note,
                $user->id,
            );

            $value = $movement->value;

            if ($value !== null && $value->isPositive()) {
                $stock = ['account' => LedgerAccount::forKey(LedgerAccount::INVENTORY)];
                $counterpart = ['account' => LedgerAccount::forKey($reason->accountKey())];

                LedgerEntry::post(
                    Str::limit(__('Ajustement d\'inventaire — :reason — :product', ['reason' => $reason->label(), 'product' => $product->display_name]), 250),
                    $isEntry
                        ? [[...$stock, 'debit' => $value], [...$counterpart, 'credit' => $value]]
                        : [[...$counterpart, 'debit' => $value], [...$stock, 'credit' => $value]],
                    $adjustment,
                    userId: $user->id,
                );
            }

            return $adjustment;
        });
    }
}
