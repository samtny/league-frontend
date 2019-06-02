<?php

use App\Association;
use Illuminate\Database\Seeder;

class BouncerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Bouncer::allow('superadmin')->everything();

        Bouncer::allow('assocadmin')->to('create', Association::class);
        Bouncer::allow('assocadmin')->toOwn(Association::class);
    }
}
