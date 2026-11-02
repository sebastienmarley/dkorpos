<?php

use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Schedule;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\SalesReport;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;

it('crée des données de démonstration dont le grand livre concorde avec les documents', function () {
    $this->seed([DatabaseSeeder::class, DemoDataSeeder::class]);

    expect(CustomerOrder::count())->toBeGreaterThan(20)
        ->and(SupplierInvoice::count())->toBeGreaterThan(5)
        ->and(Product::query()->whereNull('clean_model')->exists())->toBeFalse()
        ->and(now()->isToday())->toBeTrue()
        ->and(SalesReport::forStore(null, today(), today())->isPositive())->toBeTrue()
        ->and(User::query()->where('email', 'testuser@dkor.ca')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'vendeur@dkor.ca')->value('username'))->toBe('sophievendeuse');

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
});
