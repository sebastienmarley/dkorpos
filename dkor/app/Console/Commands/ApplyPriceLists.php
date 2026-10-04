<?php

namespace App\Console\Commands;

use App\Actions\ApplyPriceListItems;
use App\Models\PriceListList;
use Illuminate\Console\Command;

class ApplyPriceLists extends Command
{
    protected $signature = 'price-lists:apply';

    protected $description = 'Applique aux produits les listes de prix dont la date de début est atteinte';

    public function handle(ApplyPriceListItems $apply): int
    {
        $count = 0;

        PriceListList::query()
            ->whereNull('applied_at')
            ->whereHas('priceList', fn ($query) => $query->active()->whereDate('starts_on', '<=', today()))
            ->has('items')
            ->with('priceList')
            ->each(function (PriceListList $list) use ($apply, &$count): void {
                $apply->handle($list);
                $count++;
            });

        $this->info("Listes appliquées : {$count}");

        return self::SUCCESS;
    }
}
