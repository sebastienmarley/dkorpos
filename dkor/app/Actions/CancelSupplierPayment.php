<?php

namespace App\Actions;

use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\SupplierPayment;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Annule un paiement à un fournisseur (chèque retourné, erreur) : le paiement est marqué annulé, jamais supprimé, ses
 * factures redeviennent dues et son écriture est renversée par une écriture inverse (ADR 0016).
 */
class CancelSupplierPayment
{
    /**
     * @throws AuthorizationException sans la permission supplier_payments.create.
     * @throws DomainException si le paiement est déjà annulé ou si la raison manque.
     */
    public function handle(User $user, SupplierPayment $payment, string $reason): void
    {
        Gate::forUser($user)->authorize('supplier_payments.create');

        if (blank($reason)) {
            throw new DomainException(__('Expliquez l\'annulation du paiement.'));
        }

        DB::transaction(function () use ($user, $payment, $reason): void {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isCancelled()) {
                throw new DomainException(__('Ce paiement est déjà annulé.'));
            }

            $payment->update(['cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => trim($reason)]);

            $entry = LedgerEntry::query()->whereMorphedTo('reference', $payment)->with('lines.account')->sole();

            LedgerEntry::post(
                Str::limit(__('Annulation — :description', ['description' => $entry->description]), 250),
                LedgerEntry::signedLines(array_values($entry->lines->map(fn (LedgerEntryLine $line): array => [$line->account, $line->credit->minus($line->debit)])->all())),
                $payment,
                userId: $user->id,
            );
        });
    }
}
