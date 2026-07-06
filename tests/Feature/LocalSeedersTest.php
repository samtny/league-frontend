<?php

namespace Tests\Feature;

use App\Association;
use App\AssociationUser;
use App\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_seeders_create_admin_user_and_association()
    {
        $this->seed();

        $user = User::where('email', 'admin@admin.com')->first();
        $association = Association::where('subdomain', 'slope')->first();

        $this->assertNotNull($user);
        $this->assertTrue(password_verify('admin', $user->password));
        $this->assertNotNull($association);
        $this->assertSame('South Slope', $association->name);
        $this->assertSame($user->id, $association->user_id);
        $this->assertSame('home_image_file/southslope/pinball_league_photo_202607.jpg', $association->home_image_path);
        $this->assertSame("<p>KTM - League President</p>\r\n<p>SAM - Chief Technical Officer</p>", $association->about);
        $this->assertSame('rules_file/southslope/SSPL rules.pdf', $association->rules_file_path);
        $this->assertNotNull(AssociationUser::where('user_id', $user->id)->where('association_id', $association->id)->first());

        $this->actingAs($user);
        $this->assertTrue(Bouncer::can('view-admin-pages'));
        $this->assertTrue(Bouncer::can('administer-users'));
        $this->assertTrue(Bouncer::can('administer-associations'));
    }

    public function test_login_accepts_email_identifier()
    {
        $this->seed();

        $response = $this->post('/login', [
            'email' => 'admin@admin.com',
            'password' => 'admin',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', 'admin@admin.com')->first());
    }
}
