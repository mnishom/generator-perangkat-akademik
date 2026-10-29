<?php

namespace Database\Factories;

use App\Models\Fakultas;
use App\Models\Prodi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prodi>
 */
class ProdiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fakultas_id' => Fakultas::factory(),
            'kode' => strtoupper(fake()->unique()->bothify('??##')),
            'jenjang' => 'D4',
            'nama' => 'Prodi '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
