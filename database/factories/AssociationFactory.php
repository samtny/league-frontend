<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Association;
use Faker\Generator as Faker;

$factory->define(Association::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'subdomain' => strtolower($faker->word),
        'user_id' => 1,
    ];
});
