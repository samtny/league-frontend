<?php

namespace Database\Seeders;

use App\Association;
use App\Division;
use App\Team;
use App\Venue;
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

        //Bouncer::disallow('assocadmin')->to('create', Association::class);
        Bouncer::allow('assocadmin')->toOwn(Association::class);
        Bouncer::allow('assocadmin')->to('view-admin-pages');

        Bouncer::allow('assocadmin')->to('create', Division::class);
        Bouncer::allow('assocadmin')->to('edit', Division::class);
        Bouncer::allow('assocadmin')->to('update', Division::class);
        Bouncer::allow('assocadmin')->to('delete', Division::class);

        Bouncer::allow('assocadmin')->to('create', Team::class);
        Bouncer::allow('assocadmin')->to('edit', Team::class);
        Bouncer::allow('assocadmin')->to('update', Team::class);
        Bouncer::allow('assocadmin')->to('delete', Team::class);

        Bouncer::allow('assocadmin')->to('create', Venue::class);
        Bouncer::allow('assocadmin')->to('edit', Venue::class);
        Bouncer::allow('assocadmin')->to('update', Venue::class);
        Bouncer::allow('assocadmin')->to('delete', Venue::class);

        Bouncer::allow('authenticated')->to('view-users');
    }
}
