<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(BouncerSeeder::class);
        $this->call(LocalAdminUserSeeder::class);
        $this->call(LocalAssociationSeeder::class);
    }
}
