<?php

namespace App\Actions;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Passe une écriture manuelle au journal général (ADR 0016) : solde d'ouverture, dépôt d'un processeur de paiement
 * (encaisse et frais contre le compte de compensation), virement, frais bancaires, paie. Les lignes nulles sont
 * ignorées; l'écriture doit être équilibrée.
 */
class RecordJournalEntry
{
    /**
     * @param  list<array{account: LedgerAccount, debit: Money, credit: Money}>  $lines
     *
     * @throws AuthorizationException sans la permission ledger.journal.
     * @throws DomainException si la description manque, si une ligne a un débit et un crédit, ou si l'écriture n'a
     *                         pas deux lignes ou n'est pas équilibrée.
     */
    public function handle(User $user, CarbonInterface $date, string $description, ?string $documentNumber, ?int $storeId, array $lines): JournalEntry
    {
        Gate::forUser($user)->authorize('ledger.journal');

        if (blank($description)) {
            throw new DomainException(__('Décrivez l\'écriture.'));
        }

        $postedLines = [];

        foreach ($lines as $line) {
            if ($line['debit']->isNegative() || $line['credit']->isNegative() || ($line['debit']->isPositive() && $line['credit']->isPositive())) {
                throw new DomainException(__('Chaque ligne a soit un débit, soit un crédit, positif.'));
            }

            if ($line['debit']->isPositive()) {
                $postedLines[] = ['account' => $line['account'], 'debit' => $line['debit']];
            } elseif ($line['credit']->isPositive()) {
                $postedLines[] = ['account' => $line['account'], 'credit' => $line['credit']];
            }
        }

        return DB::transaction(function () use ($user, $date, $description, $documentNumber, $storeId, $postedLines): JournalEntry {
            $entry = JournalEntry::create([
                'entry_date' => $date->toDateString(),
                'description' => trim($description),
                'document_number' => filled($documentNumber) ? trim($documentNumber) : null,
                'store_id' => $storeId,
                'created_by' => $user->id,
            ]);

            LedgerEntry::post($this->ledgerDescription($entry), $postedLines, $entry, $date, $user->id, $storeId);

            return $entry;
        });
    }

    private function ledgerDescription(JournalEntry $entry): string
    {
        return Str::limit($entry->document_number !== null ? $entry->description.' ('.$entry->document_number.')' : $entry->description, 200);
    }
}
