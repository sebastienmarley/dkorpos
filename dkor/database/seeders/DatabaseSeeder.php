<?php

namespace Database\Seeders;

use App\Enums\StoreType;
use App\Enums\SupplierType;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([ReferenceDataSeeder::class, CurrencySeeder::class, TaxSeeder::class]);

        $store = Store::firstOrCreate(
            ['name' => 'Magasin principal'],
            ['type' => StoreType::Physical, 'opening_hours' => Store::defaultOpeningHours(), 'is_active' => true],
        );

        User::updateOrCreate(
            ['email' => 'testuser@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'User',
                'username' => 'testuser',
                'store_id' => $store->id,
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        )->syncRoles('salesman');

        User::updateOrCreate(
            ['email' => 'testadmin@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'Admin',
                'username' => 'testadmin',
                'store_id' => $store->id,
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        )->syncRoles('admin');

        Supplier::factory()->createMany([
            ['type' => SupplierType::Product, 'name' => 'Matériaux Leblanc'],
            ['type' => SupplierType::Product, 'name' => 'Équipements Tremblay'],
            ['type' => SupplierType::Product, 'name' => 'Fournitures Roy & Fils'],
            ['type' => SupplierType::Service, 'name' => 'Services Informatiques Gagnon'],
            ['type' => SupplierType::Shipping, 'name' => 'Transport Express Bouchard'],
        ]);

        User::updateOrCreate(
            ['email' => 'testsale@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'Sale',
                'username' => 'testsale',
                'store_id' => $store->id,
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        )->syncRoles('salesman');
    }
}
