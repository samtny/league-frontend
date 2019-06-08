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

        Bouncer::allow('admin')->to('administer-associations');
        Bouncer::allow('admin')->to('view-users');
        Bouncer::allow('admin')->to('create', Association::class);

        Bouncer::disallow('assocadmin')->to('create', Association::class);
        Bouncer::allow('assocadmin')->toOwn(Association::class);
        Bouncer::allow('assocadmin')->to('view-admin-pages');

        Bouncer::allow('authenticated')->to('view-users');
    }
}
