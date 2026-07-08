<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present()
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_registration_is_throttled()
    {
        // Exact threshold isn't the property under test (register briefly
        // authenticates mid-loop, which affects the rate limiter's key) -
        // what matters is that unlimited attempts are no longer possible.
        $statuses = [];
        for ($i = 0; $i < 10; $i++) {
            $statuses[] = $this->post('/register', [
                'name' => 'x',
                'email' => "x{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_password_reset_request_is_throttled()
    {
        $statuses = [];
        for ($i = 0; $i < 10; $i++) {
            $statuses[] = $this->post('/password/email', ['email' => 'nobody@example.com'])->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }
}
