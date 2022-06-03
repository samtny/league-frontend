<?php

namespace Database\Seeders;

use App\Association;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $slope = factory(Association::class, 1)->create([
            'subdomain' => 'slope',
        ]);
    }
}
