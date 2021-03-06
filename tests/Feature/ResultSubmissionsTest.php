<?php

namespace Tests\Feature;

use App\Association;
use App\User;
use Bouncer;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResultSubmissionsTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {
        $user = factory(User::class)->create();

        $association = factory(Association::class)->create([
            'user_id' => $user->id,
        ]);

        Bouncer::assign('assocadmin')->to($user);
        Bouncer::allow($user)->toManage($association);

        $response = $this->actingAs($user)
            ->get('/admin/results/' . $association->id . '/results/submissions');

        $user->delete();

        $association->delete();

        $response->assertStatus(200);

    }
}
