<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\Schedule;
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
    public function test_example()
    {
        Association::factory()->create(['subdomain' => 'testassoc']);

        $response = $this->get('http://testassoc.pinballleague.org/standings');

        $response->assertStatus(200);

        $response->assertSee('Standings');
    }

    public function test_no_filter_shown_when_fewer_than_two_divisions()
    {
        $association = Association::factory()->create(['subdomain' => 'testassoc']);
        $division = $this->createDivision($association, 'Only Division', '1');
        $this->createSchedule($association, $division, '2026-01-01');

        $response = $this->get('http://testassoc.pinballleague.org/standings');

        $response->assertStatus(200);
        // With only one available division there's nothing to toggle
        // between, so the heading is plain: no aria-label, no data
        // attributes, no tap-affordance class.
        $response->assertSee('Only Division');
        $response->assertDontSee('tap this heading to filter by Division', false);
        $response->assertDontSee('data-division-id', false);
        $response->assertDontSee('division-toggle', false);
        $response->assertCookieMissing('division_filter');
    }

    public function test_filter_appears_and_defaults_to_first_division_by_sequence()
    {
        $association = Association::factory()->create(['subdomain' => 'testassoc']);
        $beta = $this->createDivision($association, 'Beta', '1');
        $alpha = $this->createDivision($association, 'Alpha', '2');
        $this->createSchedule($association, $beta, '2026-01-01');
        $this->createSchedule($association, $alpha, '2026-01-02');

        $response = $this->get('http://testassoc.pinballleague.org/standings');

        $response->assertStatus(200);
        // The page defaults to filtering the schedule list down to the
        // first division by sequence, so only its heading (with a tap-to-
        // filter aria-label, since multiple divisions are available) shows.
        $response->assertSee('Beta: tap this heading to filter by Division', false);
        $response->assertDontSee('Alpha');
        $response->assertCookie('division_filter', (string) $beta->id);
        $this->assertSame(1, substr_count($response->getContent(), '<table>'));
    }

    public function test_all_divisions_cookie_shows_every_schedule()
    {
        $association = Association::factory()->create(['subdomain' => 'testassoc']);
        $beta = $this->createDivision($association, 'Beta', '1');
        $alpha = $this->createDivision($association, 'Alpha', '2');
        $this->createSchedule($association, $beta, '2026-01-01');
        $this->createSchedule($association, $alpha, '2026-01-02');

        $response = $this->withCookie('division_filter', 'all')
            ->get('http://testassoc.pinballleague.org/standings');

        $response->assertStatus(200);
        $response->assertSee('Beta');
        $response->assertSee('Alpha');
    }

    public function test_query_param_sets_cookie_and_redirects()
    {
        $association = Association::factory()->create(['subdomain' => 'testassoc']);
        $beta = $this->createDivision($association, 'Beta', '1');
        $alpha = $this->createDivision($association, 'Alpha', '2');
        $this->createSchedule($association, $beta, '2026-01-01');
        $this->createSchedule($association, $alpha, '2026-01-02');

        $response = $this->get('http://testassoc.pinballleague.org/standings?division='.$alpha->id);

        $response->assertRedirect('http://testassoc.pinballleague.org/standings');
        $response->assertCookie('division_filter', (string) $alpha->id);
    }

    public function test_invalid_query_param_falls_back_to_first_division()
    {
        $association = Association::factory()->create(['subdomain' => 'testassoc']);
        $beta = $this->createDivision($association, 'Beta', '1');
        $alpha = $this->createDivision($association, 'Alpha', '2');
        $this->createSchedule($association, $beta, '2026-01-01');
        $this->createSchedule($association, $alpha, '2026-01-02');

        $response = $this->get('http://testassoc.pinballleague.org/standings?division=999999');

        $response->assertRedirect('http://testassoc.pinballleague.org/standings');
        $response->assertCookie('division_filter', (string) $beta->id);
    }

    public function test_specific_division_hides_divisionless_schedules()
    {
        $association = Association::factory()->create(['subdomain' => 'testassoc']);
        $beta = $this->createDivision($association, 'Beta', '1');
        $alpha = $this->createDivision($association, 'Alpha', '2');
        $this->createSchedule($association, $beta, '2026-01-01');
        $this->createSchedule($association, $alpha, '2026-01-02');
        $this->createSchedule($association, null, '2026-01-03');

        $response = $this->withCookie('division_filter', (string) $beta->id)
            ->get('http://testassoc.pinballleague.org/standings');

        $response->assertStatus(200);
        $response->assertSee('Beta: tap this heading to filter by Division', false);
        $response->assertDontSee('no-division', false);
        $this->assertSame(1, substr_count($response->getContent(), '<table>'));
    }

    private function createDivision(Association $association, string $name, ?string $sequence): Division
    {
        return Division::forceCreate([
            'name' => $name,
            'association_id' => $association->id,
            'sequence' => $sequence,
        ]);
    }

    private function createSchedule(Association $association, ?Division $division, string $startDate): Schedule
    {
        return Schedule::create([
            'association_id' => $association->id,
            'division_id' => $division?->id,
            'start_date' => $startDate,
        ]);
    }
}
