<?php

namespace Database\Factories;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * super_admin/admin_staff accounts are required to have 2FA enabled
     * before they can use anything in the admin panel (security audit
     * finding D-6, EnsureAdminTwoFactorEnabled). Almost nothing outside
     * a test specifically about that requirement cares whether 2FA is
     * set up — auto-enabling it here for any factory-created admin
     * account keeps the hundreds of existing admin-panel tests focused
     * on what they actually test, instead of every one of them needing
     * its own 2FA setup boilerplate. A test that DOES need to exercise
     * the requirement itself can restore the un-enabled state afterward,
     * e.g. `$admin->forceFill(['two_factor_confirmed_at' => null])->save();`.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (in_array($user->role, ['super_admin', 'admin_staff'], true) && ! $user->two_factor_confirmed_at) {
                $user->forceFill([
                    'two_factor_secret' => 'FACTORYTESTSECRETPLACEHOLDER',
                    'two_factor_confirmed_at' => now(),
                ])->save();
            }
        });
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
}
