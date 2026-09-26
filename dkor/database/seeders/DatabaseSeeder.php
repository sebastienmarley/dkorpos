<?php

namespace Database\Seeders;

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
                'role' => 'user',
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
                'role' => 'admin',
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );

        User::updateOrCreate(
            ['email' => 'testsale@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'Sale',
                'username' => 'testsale',
                'role' => 'user',
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );
    }
}
