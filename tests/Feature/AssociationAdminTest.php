<?php

namespace Tests\Feature;

use App\Association;
use App\User;
use Bouncer;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssociationAdminTest extends TestCase
{

    private $user;
    private $association;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->user = factory(User::class)->create();

        $this->association = factory(Association::class)->create([
            'user_id' => $this->user->id,
        ]);

        Bouncer::assign('assocadmin')->to($this->user);
        Bouncer::allow($this->user)->toManage($this->association);
    }

    /**
     * Test access to view the association.
     *
     * @return void
     */
    public function testViewAssociation()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/association/' . $this->association->id);

        $response->assertStatus(200);
    }

    /**
     * Test access to view the association divisions.
     *
     * @return void
     */
    public function testViewAssociationDivisions()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/association/' . $this->association->id . '/divisions');

        $response->assertStatus(200);
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();
        $this->association->delete();
        $this->user->delete();
    }

}
