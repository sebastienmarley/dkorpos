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
        User::query()->updateOrCreate(
            ['email' => 'test@dkor.ca'],
            [
                'firstname' => 'Test',
                'lastname' => 'User',
                'username' => 'testuser',
                'role' => 'admin',
                'is_active' => true,
                'first_day' => now()->subMonths(3)->toDateString(),
                'last_day' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );
    }
}
