<?php

namespace Database\Seeders;

use App\Enums\SupplierType;
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
        $this->call([PermissionSeeder::class, RoleSeeder::class, CurrencySeeder::class]);

        User::updateOrCreate(
            ['email' => 'testuser@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'User',
                'username' => 'testuser',
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
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        )->syncRoles('salesman');
    }
}
