<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(Str::random(10)).'@harkatnegeri.ac.id',
            'google_id' => (string) fake()->unique()->numerify('#################'),
            'role' => UserRole::User,
            'status' => AccountStatus::Aktif,
            'prodi_id' => Prodi::factory(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function withoutProdi(): static
    {
        return $this->state(fn () => ['prodi_id' => null]);
    }

    public function status(AccountStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
