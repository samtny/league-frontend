<?php

namespace Tests\Feature;

use App\Association;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
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

        $response = $this->get('http://testassoc.pinballleague.org/schedule');

        $response->assertStatus(200);

        $response->assertSee('Schedule');
    }
}
