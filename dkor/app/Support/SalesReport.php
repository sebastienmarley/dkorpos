<?php

namespace App\Support;

use App\Models\CustomerOrder;
use App\Models\LedgerAccount;
use App\Models\LedgerEntryLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Ventes qui quittent le magasin : ventes de marchandises et de services passées au grand livre pour les commandes
 * clients (ADR 0016), donc au moment où l'article est remis au client (ramassé, livré, expédié, service complété),
 * avant taxes et sans les frais d'annulation. Un retour se déduit à la date où il est fait.
 */
class SalesReport
{
    /**
     * Ventes de chaque commande entre deux dates incluses, en cents, pour un magasin ou pour l'ensemble.
     *
     * @return Collection<int, int>
     */
    public static function byOrder(CarbonInterface $from, CarbonInterface $to, ?int $storeId = null): Collection
    {
        $accountIds = [LedgerAccount::forKey(LedgerAccount::MERCHANDISE_SALES)->id, LedgerAccount::forKey(LedgerAccount::SERVICE_SALES)->id];

        return LedgerEntryLine::query()
            ->join('ledger_entries', 'ledger_entries.id', '=', 'ledger_entry_lines.ledger_entry_id')
            ->where('ledger_entries.reference_type', (new CustomerOrder)->getMorphClass())
            ->whereDate('ledger_entries.posted_on', '>=', $from->toDateString())
            ->whereDate('ledger_entries.posted_on', '<=', $to->toDateString())
            ->when($storeId !== null, fn ($query) => $query->where('ledger_entries.store_id', $storeId))
            ->whereIn('ledger_entry_lines.ledger_account_id', $accountIds)
            ->groupBy('ledger_entries.reference_id')
            ->selectRaw('ledger_entries.reference_id as order_id, sum(ledger_entry_lines.credit) - sum(ledger_entry_lines.debit) as sales')
            ->pluck('sales', 'order_id')
            ->map(fn (mixed $sales): int => (int) $sales);
    }

    /** Ventes d'un magasin, ou de l'ensemble, entre deux dates incluses. */
    public static function forStore(?int $storeId, CarbonInterface $from, CarbonInterface $to): Money
    {
        return Money::of((int) self::byOrder($from, $to, $storeId)->sum());
    }

    /**
     * Ventes de chaque vendeur entre deux dates incluses, en cents : sa part de la vente de chaque commande.
     *
     * @return array<int, int>
     */
    public static function bySalesperson(CarbonInterface $from, CarbonInterface $to): array
    {
        $salesByOrder = self::byOrder($from, $to);
        $sales = [];

        foreach (CustomerOrder::query()->whereIn('id', $salesByOrder->keys())->get() as $order) {
            foreach ($order->salespeopleShares() as ['user_id' => $userId, 'percent' => $percent]) {
                $sales[$userId] = ($sales[$userId] ?? 0) + (int) round($salesByOrder[$order->id] * $percent / 100);
            }
        }

        return $sales;
    }
}
