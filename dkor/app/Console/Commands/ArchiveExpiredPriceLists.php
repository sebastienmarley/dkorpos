<?php

namespace App\Console\Commands;

use App\Models\PriceList;
use Illuminate\Console\Command;

class ArchiveExpiredPriceLists extends Command
{
    protected $signature = 'price-lists:archive-expired';

    protected $description = 'Archive les listes de prix dont la date de fin est passée';

    public function handle(): int
    {
        $count = PriceList::query()
            ->active()
            ->whereDate('ends_on', '<', today())
            ->update(['archived_at' => now()]);

        $this->info("Listes de prix archivées : {$count}");

        return self::SUCCESS;
    }
}
