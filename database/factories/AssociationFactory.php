<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssociationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            // Faker's word() pool is small and its unique() tracking isn't
            // reliably shared across every factory call in a test run, so
            // collisions against the DB-level unique constraint are
            // possible; the random suffix makes collisions a non-issue
            // regardless of Faker's internal state.
            'subdomain' => strtolower($this->faker->word) . '-' . Str::random(8),
            'user_id' => User::factory(),
        ];
    }
}
