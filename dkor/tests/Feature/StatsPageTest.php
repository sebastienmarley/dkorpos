<?php

use App\Livewire\Stats\Index;
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
    Carbon::setTestNow('2026-03-15 10:00');
    (new TaxSeeder)->run();
    $this->store = Store::factory()->create(['name' => 'Rimouski']);
    $this->otherStore = Store::factory()->create(['name' => 'Matane']);
    $this->sophie = User::factory()->withRole('salesman')->create(['store_id' => $this->store->id, 'firstname' => 'Sophie', 'lastname' => 'Vendeuse']);
    $this->marc = User::factory()->withRole('salesman')->create(['store_id' => $this->otherStore->id, 'firstname' => 'Marc', 'lastname' => 'Vendeur']);
});

/**
 * Commande vendue par un vendeur, avec une vente passée au grand livre à cette date.
 */
function venteStats(Store $store, User $seller, string $date, float $amount): void
{
    $order = CustomerOrder::factory()->create(['store_id' => $store->id]);
    $order->syncSalespeople([['user_id' => $seller->id, 'percent' => 100]]);
    $money = Money::parse($amount);

    LedgerEntry::post('Vente', [
        ['account' => LedgerAccount::forKey(LedgerAccount::CUSTOMER_DEPOSITS), 'debit' => $money],
        ['account' => LedgerAccount::forKey(LedgerAccount::MERCHANDISE_SALES), 'credit' => $money],
    ], $order, Carbon::parse($date), storeId: $store->id);
}

/**
 * Ventes de chaque mois affiché, en cents.
 *
 * @return list<int>
 */
function moisStats($component): array
{
    return array_map(fn (array $row): int => $row['sales']->cents(), $component->viewData('months'));
}

it('ouvre la page à tous les employés', function () {
    $this->actingAs(User::factory()->withRole('warehouse')->create())
        ->get(route('stats.index'))
        ->assertOk()
        ->assertSee('Aucune statistique de ventes à afficher pour vous.');
});

it('montre au vendeur ses ventes par mois de l\'année, comparées à l\'année précédente, sans sélecteur', function () {
    venteStats($this->store, $this->sophie, '2026-01-10', 100);
    venteStats($this->store, $this->sophie, '2026-03-14', 300);
    venteStats($this->store, $this->sophie, '2025-02-20', 50);
    venteStats($this->store, $this->marc, '2026-02-01', 999);

    $this->actingAs($this->sophie);
    $component = Livewire::test(Index::class);

    expect(moisStats($component))->toBe([10000, 0, 30000])
        ->and($component->viewData('total')->cents())->toBe(40000)
        ->and($component->viewData('previousTotal')->cents())->toBe(5000)
        ->and($component->viewData('years'))->toBe([2026, 2025])
        ->and($component->viewData('sellers'))->toBeEmpty();

    expect(moisStats($component->set('year', 2025)))->toHaveCount(12)
        ->and($component->viewData('total')->cents())->toBe(5000);
});

it('montre au directeur son magasin, puis un de ses vendeurs au choix, jamais celui d\'un autre magasin', function () {
    $manager = User::factory()->withRole('manager')->create(['store_id' => $this->store->id]);
    venteStats($this->store, $this->sophie, '2026-01-10', 100);
    venteStats($this->store, $manager, '2026-01-11', 20);
    venteStats($this->otherStore, $this->marc, '2026-01-12', 999);

    $this->actingAs($manager);
    $component = Livewire::test(Index::class);

    expect($component->viewData('subject')['label'])->toBe('Ventes du magasin — Rimouski')
        ->and(moisStats($component)[0])->toBe(12000)
        ->and($component->viewData('sellers')->pluck('id')->all())->toEqualCanonicalizing([$this->sophie->id, $manager->id])
        ->and($component->viewData('stores'))->toBeEmpty();

    $component->set('sellerFilter', (string) $this->sophie->id);
    expect(moisStats($component)[0])->toBe(10000);

    $component->set('sellerFilter', (string) $this->marc->id);
    expect($component->viewData('subject')['label'])->toBe('Ventes du magasin — Rimouski')
        ->and(moisStats($component)[0])->toBe(12000);
});

it('laisse choisir le magasin avec stores.all, puis un vendeur de ce magasin', function () {
    $owner = User::factory()->withRole('owner')->create(['store_id' => $this->store->id]);
    venteStats($this->store, $this->sophie, '2026-01-10', 100);
    venteStats($this->otherStore, $this->marc, '2026-01-12', 50);

    $this->actingAs($owner);
    $component = Livewire::test(Index::class);

    expect(moisStats($component)[0])->toBe(15000)
        ->and($component->viewData('stores'))->toHaveCount(2);

    $component->set('storeFilter', (string) $this->otherStore->id);
    expect(moisStats($component)[0])->toBe(5000)
        ->and($component->viewData('sellers')->pluck('id')->all())->toBe([$this->marc->id]);

    $component->set('sellerFilter', (string) $this->marc->id)
        ->set('storeFilter', (string) $this->store->id);
    expect($component->get('sellerFilter'))->toBe('')
        ->and(moisStats($component)[0])->toBe(10000);
});
