<?php

namespace Tests\Feature;

use Tests\TestCase;

class AnonymousTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_example()
    {
        $response = $this->get('/admin');

        $response->assertStatus(302);
    }
}
