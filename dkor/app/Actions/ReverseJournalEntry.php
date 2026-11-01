<?php

namespace App\Actions;

use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Renverse une écriture manuelle (erreur de saisie) : une nouvelle écriture manuelle passe l'inverse de son écriture
 * au grand livre, à la date choisie. L'écriture renversée reste visible.
 */
class ReverseJournalEntry
{
    /**
     * @throws AuthorizationException sans la permission ledger.journal.
     * @throws DomainException si l'écriture est déjà renversée ou est elle-même un renversement.
     */
    public function handle(User $user, JournalEntry $entry, CarbonInterface $date): JournalEntry
    {
        Gate::forUser($user)->authorize('ledger.journal');

        return DB::transaction(function () use ($user, $entry, $date): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->with('ledgerEntry.lines.account')->findOrFail($entry->id);

            if ($entry->reverses_id !== null) {
                throw new DomainException(__('Un renversement ne se renverse pas : passez une nouvelle écriture.'));
            }

            if ($entry->reversal()->exists()) {
                throw new DomainException(__('Cette écriture est déjà renversée.'));
            }

            $reversal = JournalEntry::create([
                'entry_date' => $date->toDateString(),
                'description' => Str::limit(__('Renversement — :description', ['description' => $entry->description]), 250),
                'document_number' => $entry->document_number,
                'store_id' => $entry->store_id,
                'reverses_id' => $entry->id,
                'created_by' => $user->id,
            ]);

            LedgerEntry::post(
                $reversal->description,
                LedgerEntry::signedLines(array_values($entry->ledgerEntry?->lines->map(fn (LedgerEntryLine $line): array => [$line->account, $line->credit->minus($line->debit)])->all() ?? [])),
                $reversal,
                $date,
                $user->id,
                $entry->store_id,
            );

            return $reversal;
        });
    }
}
