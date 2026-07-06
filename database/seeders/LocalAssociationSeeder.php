<?php

namespace Database\Seeders;

use App\Association;
use App\AssociationUser;
use App\Member;
use App\Team;
use App\User;
use App\Venue;
use Illuminate\Database\Seeder;

class LocalAssociationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $owner = User::where('email', 'admin@admin.com')->first();

        if (! $owner) {
            return;
        }

        $association = Association::updateOrCreate(
            ['subdomain' => 'slope'],
            [
                'name' => 'South Slope',
                'user_id' => $owner->id,
                'home_image_path' => 'home_image_file/southslope/pinball_league_photo_202607.jpg',
                'about' => "<p>KTM - League President</p>\r\n<p>SAM - Chief Technical Officer</p>",
                'rules_file_path' => 'rules_file/southslope/SSPL rules.pdf',
            ]
        );

        AssociationUser::updateOrCreate(
            [
                'user_id' => $owner->id,
                'association_id' => $association->id,
            ],
            []
        );

        $buttermilk = Team::updateOrCreate(
            [
                'association_id' => $association->id,
                'name' => 'Buttermilk',
            ],
            [
                'association_id' => $association->id,
                'name' => 'Buttermilk',
            ]
        );

        Member::updateOrCreate(
            [
                'association_id' => $association->id,
                'team_id' => $buttermilk->id,
                'name' => 'Sam',
            ],
            [
                'association_id' => $association->id,
                'team_id' => $buttermilk->id,
                'name' => 'Sam',
                'role' => 'Player',
            ]
        );

        Member::updateOrCreate(
            [
                'association_id' => $association->id,
                'team_id' => $buttermilk->id,
                'name' => 'Kate',
            ],
            [
                'association_id' => $association->id,
                'team_id' => $buttermilk->id,
                'name' => 'Kate',
                'role' => 'Player',
            ]
        );

        Team::updateOrCreate(
            [
                'association_id' => $association->id,
                'name' => "Rullo's",
            ],
            [
                'association_id' => $association->id,
                'name' => "Rullo's",
            ]
        );

        Venue::updateOrCreate(
            [
                'association_id' => $association->id,
                'name' => 'Buttermilk',
            ],
            [
                'association_id' => $association->id,
                'name' => 'Buttermilk',
            ]
        );

        Venue::updateOrCreate(
            [
                'association_id' => $association->id,
                'name' => "Rullo's",
            ],
            [
                'association_id' => $association->id,
                'name' => "Rullo's",
            ]
        );

        \Bouncer::assign('assocadmin')->to($owner);
        \Bouncer::allow($owner)->toManage($association);
    }
}
