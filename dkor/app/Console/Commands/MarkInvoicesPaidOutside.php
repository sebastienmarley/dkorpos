<?php

namespace App\Console\Commands;

use App\Models\SupplierInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Bascule vers la tenue des paiements (#132) : les factures datées d'avant la date donnée, payées hors de
 * l'application, n'apparaissent plus dans les comptes à payer. Une facture au grand livre n'est pas touchée : elle se
 * paie par un paiement.
 */
class MarkInvoicesPaidOutside extends Command
{
    protected $signature = 'invoices:mark-paid-outside {--before= : Date (AAAA-MM-JJ) : les factures datées d\'avant sont payées}';

    protected $description = 'Marque payées hors de l\'application les factures fournisseur d\'avant une date, sans écriture au grand livre';

    public function handle(): int
    {
        $before = (string) $this->option('before');

        if (! Carbon::canBeCreatedFromFormat($before, 'Y-m-d')) {
            $this->error('Indiquez la date de bascule : --before=AAAA-MM-JJ');

            return self::FAILURE;
        }

        $marked = 0;
        $skipped = 0;

        SupplierInvoice::query()
            ->unpaid()
            ->whereDate('invoice_date', '<', $before)
            ->with('paymentAllocations.payment')
            ->each(function (SupplierInvoice $invoice) use (&$marked, &$skipped): void {
                if ($invoice->hasLedgerEntry() || $invoice->activeAllocations()->isNotEmpty()) {
                    $skipped++;

                    return;
                }

                $invoice->markPaidOutside();
                $marked++;
            });

        $this->info("Factures marquées payées : {$marked}");

        if ($skipped > 0) {
            $this->warn("Factures au grand livre ou déjà en partie payées, laissées dues : {$skipped}");
        }

        return self::SUCCESS;
    }
}
