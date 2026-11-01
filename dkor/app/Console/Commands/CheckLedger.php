<?php

namespace App\Console\Commands;

use App\Support\LedgerChecks;
use Illuminate\Console\Command;

/**
 * Contrôles d'exactitude du grand livre (ADR 0016) : compare le solde des comptes de contrôle à la valeur que donnent
 * les documents et échoue au premier écart.
 */
class CheckLedger extends Command
{
    protected $signature = 'ledger:check';

    protected $description = 'Vérifie que les comptes de contrôle du grand livre égalent les documents (stock, achats non facturés, dépôts, fournisseurs)';

    public function handle(LedgerChecks $checks): int
    {
        $failed = false;

        foreach ($checks->run() as $check) {
            $ok = $check['ledger']->equals($check['expected']);
            $failed = $failed || ! $ok;

            $this->line(sprintf('%s %s — %s : grand livre %s, documents %s', $ok ? '✔' : '✘', $check['invariant'], $check['label'], $check['ledger']->format(), $check['expected']->format()));
        }

        if ($failed) {
            $this->error('Le grand livre ne concorde pas avec les documents.');

            return self::FAILURE;
        }

        $this->info('Le grand livre concorde avec les documents.');

        return self::SUCCESS;
    }
}
