<?php

namespace Tests\Feature;

use App\Association;
use App\ContactSubmission;
use App\Division;
use App\Series;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a production bug where Association::activeSchedules()
 * and Association::activeContactSubmissions() built an ungrouped
 * ->where(...)->orWhereNull('archived') clause. Because it wasn't parenthesized,
 * Eloquent generated `association_id = ? AND archived != ? OR archived IS NULL`,
 * which (per SQL operator precedence) matches any row with a null `archived`
 * column regardless of association - silently leaking every association's
 * "active" schedules/standings/contact submissions into every other
 * association's pages.
 */
class AssociationScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_standings_and_schedule_pages_do_not_leak_other_associations_schedules()
    {
        $associationA = Association::factory()->create(['subdomain' => 'assoc-a']);
        $associationB = Association::factory()->create(['subdomain' => 'assoc-b']);

        $seriesA = Series::create(['name' => 'Series A', 'association_id' => $associationA->id]);
        $seriesB = Series::create(['name' => 'Series B', 'association_id' => $associationB->id]);

        $divisionA = new Division(['name' => 'Division Alpha']);
        $divisionA->association_id = $associationA->id;
        $divisionA->save();

        $divisionB = new Division(['name' => 'Division Bravo']);
        $divisionB->association_id = $associationB->id;
        $divisionB->save();

        // archived left NULL on both, matching real-world data (nullable column, rarely set explicitly).
        $associationA->schedules()->create([
            'name' => 'Schedule Alpha', 'series_id' => $seriesA->id, 'division_id' => $divisionA->id,
            'start_date' => now()->subDay(), 'end_date' => now()->addWeek(),
        ]);
        $associationB->schedules()->create([
            'name' => 'Schedule Bravo', 'series_id' => $seriesB->id, 'division_id' => $divisionB->id,
            'start_date' => now()->subDay(), 'end_date' => now()->addWeek(),
        ]);

        $standingsA = $this->get('http://assoc-a.pinballleague.org/standings');
        $standingsA->assertStatus(200);
        $standingsA->assertSee('Division Alpha')->assertDontSee('Division Bravo');

        $scheduleA = $this->get('http://assoc-a.pinballleague.org/schedule');
        $scheduleA->assertStatus(200);
        $scheduleA->assertSee('Division Alpha')->assertDontSee('Division Bravo');

        $this->assertCount(1, $associationA->activeSchedules()->get());
        $this->assertCount(1, $associationB->activeSchedules()->get());
    }

    public function test_contact_submissions_listing_does_not_leak_other_associations_submissions()
    {
        $this->seed();
        $admin = User::where('email', 'admin@admin.com')->first();
        $associationA = Association::where('subdomain', 'slope')->first();
        $associationB = Association::factory()->create(['subdomain' => 'other-assoc']);

        ContactSubmission::create(['email' => 'a@example.com', 'reason' => 'other', 'comment' => 'from A', 'association_id' => $associationA->id]);
        ContactSubmission::create(['email' => 'b@example.com', 'reason' => 'other', 'comment' => 'from B', 'association_id' => $associationB->id]);

        $this->actingAs($admin);

        $response = $this->get(route('contact_submissions.list', $associationA));
        $response->assertStatus(200);
        $response->assertSee('a@example.com')->assertDontSee('b@example.com');

        $this->assertCount(1, $associationA->activeContactSubmissions()->get());
        $this->assertCount(1, $associationB->activeContactSubmissions()->get());
    }
}
