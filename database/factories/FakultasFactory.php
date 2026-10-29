<?php

namespace Database\Factories;

use App\Models\Fakultas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fakultas>
 */
class FakultasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Fakultas '.fake()->unique()->word(),
        ];
    }
}
