<?php

namespace App\Actions;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryCountStatus;
use App\Enums\InventoryStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Finalise une prise d'inventaire annuelle (issue #88, ADR 0011), dans une seule transaction : l'attendu de chaque ligne
 * est figé, puis chaque écart devient un ajustement daté du jour du comptage. Un manque est une perte (« Perte à
 * l'inventaire annuel », Pertes d'inventaire / Stock, au coût FIFO); un surplus, une correction de comptage (Stock /
 * Écart d'inventaire, au coût du produit).
 *
 * Refusée tant que le stock n'est pas gelé, qu'une portée des feuilles de compte n'est pas réconciliée, qu'une ligne
 * n'est ni comptée ni conservée, ou qu'une ligne « Stock client » a un écart : ce stock appartient à une commande, qu'il
 * faut corriger d'abord.
 */
class AnnualInventoryFinalUpdate
{
    public function __construct(private AdjustInventory $adjustInventory) {}

    /**
     * @throws AuthorizationException sans la permission inventory.adjust.
     * @throws DomainException si la prise ne peut pas être finalisée.
     */
    public function handle(InventoryCount $count, User $user): InventoryCount
    {
        Gate::forUser($user)->authorize('inventory.adjust');

        return DB::transaction(function () use ($count, $user): InventoryCount {
            $count->refreshForUpdate();
            $count->guardInProgress();

            if ($count->stock_frozen_at === null) {
                throw new DomainException(__('Gelez et comptez le stock (après la fermeture) avant de finaliser.'));
            }

            $unreconciled = $count->unreconciledScopeLabels();

            if ($unreconciled !== []) {
                throw new DomainException(__('Portées à réconcilier avant de finaliser : :scopes.', ['scopes' => implode(', ', $unreconciled)]));
            }

            $count->syncLines(InventoryCount::countedStatuses());

            foreach (InventoryCount::countedStatuses() as $status) {
                if ($count->hasSheetsFor($status)) {
                    $count->applyResults($status, $user);
                }
            }

            $lines = $count->lines()->with('product.inventoryStock')->get();

            $uncounted = $lines->reject(fn (InventoryCountLine $line): bool => $line->isCounted());

            if ($uncounted->isNotEmpty()) {
                throw new DomainException(trans_choice(
                    ':count ligne n\'est ni comptée ni conservée.|:count lignes ne sont ni comptées ni conservées.',
                    $uncounted->count(),
                ));
            }

            $customerVariances = $lines->filter(fn (InventoryCountLine $line): bool => $line->status === InventoryStatus::ReservedCustomer && ($line->difference() ?? 0) !== 0);

            if ($customerVariances->isNotEmpty()) {
                throw new DomainException(__('Écart dans le stock client (:products) : corrigez d\'abord la réservation dans la commande du client.', [
                    'products' => $customerVariances->map(fn (InventoryCountLine $line): string => $line->product->display_name)->implode(', '),
                ]));
            }

            $count->update([
                'status' => InventoryCountStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $expected = $line->expected();
                $difference = $line->is_kept ? 0 : (int) $line->counted_quantity - $expected;

                $adjustment = $difference === 0 ? null : $this->adjustInventory->handle(
                    $user,
                    $line->product,
                    $difference < 0 ? InventoryAdjustmentReason::AnnualCountLoss : InventoryAdjustmentReason::CountCorrection,
                    $line->status,
                    $difference > 0,
                    abs($difference),
                    Str::limit(__('Prise d\'inventaire du :date', ['date' => $count->counted_on->toDateString()]).($line->note ? ' — '.$line->note : ''), 250),
                    $count->counted_on,
                );

                $line->update([
                    'expected_quantity' => $expected,
                    'inventory_adjustment_id' => $adjustment?->id,
                ]);
            }

            return $count;
        });
    }
}
