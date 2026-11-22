<?php

use App\Actions\ImportPriceListCsv;
use App\Enums\StoreType;
use App\Models\CustomerOrder;
use App\Models\Department;
use App\Models\PriceList;
use App\Models\PriceListList;
use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\ProductDemo;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\SalesReport;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

it('crée des données de démonstration dont le grand livre concorde avec les documents', function () {
    $this->seed([DatabaseSeeder::class, DemoDataSeeder::class]);

    expect(CustomerOrder::count())->toBeGreaterThan(200)
        ->and(SupplierInvoice::count())->toBeGreaterThan(5)
        ->and(Product::query()->whereNull('clean_model')->exists())->toBeFalse()
        ->and(Product::query()->whereNull('category_id')->exists())->toBeFalse()
        ->and(Department::query()->where('name', 'Électroménagers')->sole()->categories()->pluck('name')->sort()->values()->all())
        ->toBe(['Congélateurs', 'Laveuses', 'Poêles', 'Réfrigérateurs', 'Sécheuses'])
        ->and(Supplier::query()->whereIn('name', ['Électro Côte-Nord', 'Great Lakes Appliances (USD)'])->get()->every(
            fn (Supplier $supplier): bool => $supplier->products()->exists()
                && ! $supplier->products()->whereRelation('category.department', 'name', '!=', 'Électroménagers')->exists()
        ))->toBeTrue()
        ->and(now()->isToday())->toBeTrue()
        ->and(SalesReport::forStore(null, today(), today())->isPositive())->toBeTrue()
        ->and(User::query()->where('email', 'testuser@dkor.ca')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'vendeur@dkor.ca')->value('username'))->toBe('sophievendeuse');

    $headOffice = Store::query()->where('type', StoreType::Virtual)->sole();
    $salesStores = Store::query()->where('type', StoreType::Physical)->get();
    expect($salesStores)->toHaveCount(3)
        ->and(CustomerOrder::query()->where('store_id', $headOffice->id)->exists())->toBeFalse()
        ->and($salesStores->every(fn (Store $store): bool => $store->billing_store_id === $headOffice->id && CustomerOrder::query()->where('store_id', $store->id)->exists()))->toBeTrue()
        ->and($salesStores->whereNotNull('warehouse_store_id'))->toHaveCount(1)
        ->and(StoreTransfer::query()->exists())->toBeTrue()
        ->and(ProductDemo::query()->exists())->toBeTrue()
        ->and(ProductClearance::query()->exists())->toBeTrue();

    foreach (array_keys(config('access.roles')) as $role) {
        expect(User::role($role)->exists())->toBeTrue("Aucun employé avec le rôle {$role}");
    }

    $driverDays = Schedule::query()->whereIn('user_id', User::role('delivery')->pluck('id'))->pluck('date');
    expect($driverDays)->not->toBeEmpty()
        ->and($driverDays->filter(fn (string $date): bool => Carbon::parse($date)->isWeekend()))->toBeEmpty();

    $weeklyMinutes = Schedule::query()
        ->whereIn('user_id', User::role(['salesman', 'design'])->pluck('id'))
        ->get()
        ->groupBy(fn (Schedule $shift): string => $shift->user_id.'-'.Carbon::parse($shift->date)->startOfWeek()->toDateString())
        ->map(fn ($shifts): int => $shifts->sum(fn (Schedule $shift): int => (int) Carbon::parse($shift->start_time)->diffInMinutes(Carbon::parse($shift->end_time)) - $shift->break_minutes));
    expect($weeklyMinutes)->not->toBeEmpty()
        ->and($weeklyMinutes->max())->toBeLessThanOrEqual(40 * 60);

    $this->artisan('ledger:check')->expectsOutputToContain('concorde avec les documents')->assertSuccessful();

    // Listes de prix de démo : une par fournisseur, 50 lignes dont ses 15 produits existants.
    $priceListFiles = glob(database_path('seeders/price-lists/*.csv'));
    $suppliersBySlug = Supplier::query()->has('products', '>=', 15)->get()->keyBy(fn (Supplier $supplier): string => Str::slug($supplier->name));

    expect($priceListFiles)->toHaveCount($suppliersBySlug->count());

    foreach ($priceListFiles as $file) {
        $supplier = $suppliersBySlug->get(basename($file, '.csv'));
        $list = PriceListList::factory()->for(PriceList::factory()->for($supplier))->create();

        expect(app(ImportPriceListCsv::class)->handle($list, $file))
            ->toMatchArray(['rows' => 50, 'matched' => 15, 'duplicates' => 0]);
    }
})->group('demo-seeder');
