<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

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
            'subdomain' => strtolower($this->faker->word),
            'user_id' => 1,
        ];
    }
}
