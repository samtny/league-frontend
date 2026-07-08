<?php

namespace Tests\Feature;

use App\Association;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {
        Association::factory()->create(['subdomain' => 'testassoc']);

        $response = $this->get('http://testassoc.pinballleague.org/standings');

        $response->assertStatus(200);

        $response->assertSee('Standings');
    }
}
