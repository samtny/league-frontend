<?php

namespace Tests\Feature;

use App\Association;
use App\ContactSubmission;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the public /contact form's security posture:
 *
 * - Stored XSS: the admin "Messages" list
 *   (resources/views/association/contact_submissions.blade.php) used to render
 *   email/reason/comment via raw <?php echo ?> string concatenation instead of
 *   Blade's auto-escaping {{ }}. All three fields are attacker-controlled from
 *   the public, unauthenticated /contact form, so a script payload in any of
 *   them would execute in an authenticated admin's browser on page load.
 * - association_id spoofing: ContactController::contactSubmit() used to trust
 *   the hidden `association_id` form field verbatim instead of the association
 *   already resolved from the request's subdomain, letting a submission on one
 *   association's page be filed under a different (real) association's inbox.
 * - No rate limiting: the POST route had no throttle, only the honeypot-based
 *   bot check.
 */
class ContactSubmissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submissions_list_escapes_script_payload()
    {
        $this->seed();
        $admin = User::where('email', 'admin@admin.com')->first();
        $association = Association::where('subdomain', 'slope')->first();

        ContactSubmission::create([
            'email' => 'attacker@example.com',
            'reason' => 'other',
            'comment' => '<script>alert(document.cookie)</script>',
            'association_id' => $association->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('contact_submissions.list', $association));
        $response->assertStatus(200);

        // Raw payload must NOT appear unescaped.
        $response->assertDontSee('<script>alert(document.cookie)</script>', false);
        // Escaped form must appear instead.
        $response->assertSee('&lt;script&gt;alert(document.cookie)&lt;/script&gt;', false);
    }

    public function test_association_id_cannot_be_spoofed()
    {
        $associationA = Association::factory()->create(['subdomain' => 'contact-a']);
        $associationB = Association::factory()->create(['subdomain' => 'contact-b']);

        // Submitting on A's subdomain but claiming association_id = B in the body.
        $response = $this->post('http://contact-a.pinballleague.org/contact', [
            'email' => 'test@example.com',
            'reason' => 'feedback',
            'comment' => 'hi',
            'association_id' => $associationB->id,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('contact_submissions', [
            'email' => 'test@example.com',
            'association_id' => $associationA->id,
        ]);
        $this->assertDatabaseMissing('contact_submissions', [
            'email' => 'test@example.com',
            'association_id' => $associationB->id,
        ]);
    }

    public function test_contact_submit_is_rate_limited()
    {
        Association::factory()->create(['subdomain' => 'contact-throttle']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('http://contact-throttle.pinballleague.org/contact', [
                'email' => 'test'.$i.'@example.com',
                'reason' => 'feedback',
                'comment' => 'hi',
            ]);
            $response->assertStatus(302);
        }

        $sixth = $this->post('http://contact-throttle.pinballleague.org/contact', [
            'email' => 'test6@example.com',
            'reason' => 'feedback',
            'comment' => 'hi',
        ]);
        $sixth->assertStatus(429);
    }
}
