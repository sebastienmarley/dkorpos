<?php

namespace Database\Seeders;

use App\Enums\RoleType;
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
        User::updateOrCreate(
            ['email' => 'testuser@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'User',
                'username' => 'testuser',
                'role' => RoleType::Salesman,
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );

        User::updateOrCreate(
            ['email' => 'testadmin@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'Admin',
                'username' => 'testadmin',
                'role' => RoleType::Admin,
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );

        Supplier::factory()->createMany([
            ['type' => SupplierType::Product, 'name' => 'Matériaux Leblanc'],
            ['type' => SupplierType::Product, 'name' => 'Équipements Tremblay'],
            ['type' => SupplierType::Product, 'name' => 'Fournitures Roy & Fils'],
            ['type' => SupplierType::Service, 'name' => 'Services Informatiques Gagnon'],
            ['type' => SupplierType::Service, 'name' => 'Transport Express Bouchard'],
        ]);

        User::updateOrCreate(
            ['email' => 'testsale@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'Sale',
                'username' => 'testsale',
                'role' => RoleType::Salesman,
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );
    }
}
