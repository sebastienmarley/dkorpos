<?php

use App\Livewire\SalesStats;
use App\Models\CustomerOrder;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-11-18 10:00');
    (new TaxSeeder)->run();
    $this->store = Store::factory()->create(['name' => 'Rimouski']);
    $this->otherStore = Store::factory()->create(['name' => 'Matane']);
});

/**
 * Passe au grand livre une vente (montant positif) ou un retour (négatif) de la commande, à cette date.
 */
function venteAccueil(CustomerOrder $order, string $date, float $amount): void
{
    $money = Money::parse(abs($amount));
    $sales = LedgerAccount::forKey(LedgerAccount::MERCHANDISE_SALES);
    $deposits = LedgerAccount::forKey(LedgerAccount::CUSTOMER_DEPOSITS);

    LedgerEntry::post('Vente', $amount > 0
        ? [['account' => $deposits, 'debit' => $money], ['account' => $sales, 'credit' => $money]]
        : [['account' => $sales, 'debit' => $money], ['account' => $deposits, 'credit' => $money]], $order, Carbon::parse($date), storeId: $order->store_id);
}

it('montre au vendeur sa part des ventes du jour, de la semaine et du mois, sans celles du magasin', function () {
    $seller = User::factory()->withRole('salesman')->create(['store_id' => $this->store->id]);
    $colleague = User::factory()->withRole('salesman')->create(['store_id' => $this->store->id]);

    $shared = CustomerOrder::factory()->create(['store_id' => $this->store->id]);
    $shared->syncSalespeople([['user_id' => $seller->id, 'percent' => 50], ['user_id' => $colleague->id, 'percent' => 50]]);
    venteAccueil($shared, '2026-11-18', 200);
    venteAccueil($shared, '2026-11-16', 1000);

    $own = CustomerOrder::factory()->create(['store_id' => $this->store->id]);
    $own->syncSalespeople([['user_id' => $seller->id, 'percent' => 100]]);
    venteAccueil($own, '2026-11-03', 3000);
    venteAccueil($own, '2026-11-17', -500);
    venteAccueil($own, '2026-10-31', 9000);

    $this->actingAs($seller);

    Livewire::test(SalesStats::class)
        ->assertViewHas('ownSales', fn (array $sales): bool => array_map(fn (Money $amount): int => $amount->cents(), array_values($sales)) === [10000, 10000, 310000])
        ->assertViewHas('storeSales', null)
        ->assertSee('Mes ventes')
        ->assertDontSee('Ventes du magasin');
});

it('montre au directeur les ventes de son magasin seulement', function () {
    $manager = User::factory()->withRole('manager')->create(['store_id' => $this->store->id]);

    venteAccueil(CustomerOrder::factory()->create(['store_id' => $this->store->id]), '2026-11-18', 150);
    venteAccueil(CustomerOrder::factory()->create(['store_id' => $this->store->id]), '2026-11-10', 400);
    venteAccueil(CustomerOrder::factory()->create(['store_id' => $this->otherStore->id]), '2026-11-18', 999);

    $this->actingAs($manager);

    Livewire::test(SalesStats::class)
        ->assertViewHas('ownSales', null)
        ->assertViewHas('storeSales', fn (array $stats): bool => $stats['name'] === 'Rimouski'
            && array_map(fn (Money $amount): int => $amount->cents(), array_values($stats['sales'])) === [15000, 15000, 55000])
        ->assertDontSee('Matane');
});

it('montre tous les magasins, ou celui choisi, à qui a stores.all', function () {
    $owner = User::factory()->withRole('owner')->create(['store_id' => $this->store->id]);

    venteAccueil(CustomerOrder::factory()->create(['store_id' => $this->store->id]), '2026-11-18', 150);
    venteAccueil(CustomerOrder::factory()->create(['store_id' => $this->otherStore->id]), '2026-11-18', 50);

    $this->actingAs($owner);

    Livewire::test(SalesStats::class)
        ->assertViewHas('storeSales', fn (array $stats): bool => $stats['sales'][__('Aujourd\'hui')]->cents() === 20000)
        ->set('storeFilter', (string) $this->otherStore->id)
        ->assertViewHas('storeSales', fn (array $stats): bool => $stats['name'] === 'Matane' && $stats['sales'][__('Aujourd\'hui')]->cents() === 5000);
});

it('ne montre aucune vente à un employé qui ne vend pas et n\'a pas sales.view_store', function () {
    $this->actingAs(User::factory()->withRole('warehouse')->create(['store_id' => $this->store->id]));

    Livewire::test(SalesStats::class)
        ->assertViewHas('ownSales', null)
        ->assertViewHas('storeSales', null);
});
