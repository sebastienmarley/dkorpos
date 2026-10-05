<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'store_id' => Store::factory(),
            'is_active' => true,
            'first_day' => now()->subMonths(6)->toDateString(),
            'last_day' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Sans autre rôle précisé, un usager de test est vendeur.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->roles()->doesntExist()) {
                $user->assignRole('salesman');
            }
        });
    }

    /**
     * Indicate that the user has the given role (nom technique : admin, manager, salesman…).
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles($role));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(Str::random(32)),
            'two_factor_recovery_codes' => encrypt(json_encode(['one', 'two', 'three'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
