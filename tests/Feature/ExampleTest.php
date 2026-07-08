<?php

namespace Tests\Feature;

use App\Association;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        Association::factory()->create(['subdomain' => 'testassoc']);

        $response = $this->get('http://testassoc.pinballleague.org/');

        $response->assertStatus(200);
    }
}
