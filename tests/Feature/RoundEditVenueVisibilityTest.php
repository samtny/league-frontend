<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\User;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundEditVenueVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_venue_without_a_match_is_suppressed_but_one_with_a_match_still_shows()
    {
        $association = Association::factory()->create(['subdomain' => 'round-edit-venues']);

        $division = new Division(['name' => 'Round Edit Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Round Edit Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Round Edit Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
        ]);

        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $series->id;
        $round->division_id = $division->id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        $activeVenue = Venue::create(['name' => 'Active Venue', 'association_id' => $association->id, 'active' => true]);
        $inactiveVenueWithMatch = Venue::create(['name' => 'Inactive Venue With Match', 'association_id' => $association->id, 'active' => false]);
        $inactiveVenueWithoutMatch = Venue::create(['name' => 'Inactive Venue Without Match', 'association_id' => $association->id, 'active' => false]);

        $this->createMatch($association, $series, $division, $schedule, $round, $activeVenue, 'Active Venue Match');

        // Simulates a venue that was active when its match was generated and
        // has since been deactivated - the match still exists and must stay
        // editable even though the venue itself is now inactive.
        $this->createMatch($association, $series, $division, $schedule, $round, $inactiveVenueWithMatch, 'Inactive Venue Match');

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        $response = $this->get(route('round.edit', ['association' => $association, 'schedule' => $schedule, 'round' => $round]));

        $response->assertOk();
        $response->assertSee('Active Venue');
        $response->assertSee('Inactive Venue With Match');
        $response->assertDontSee('Inactive Venue Without Match');
    }

    /**
     * sequence isn't in PLMatch::$fillable (see Round::createMatches(),
     * which sets it via direct property assignment instead of mass
     * assignment), so PLMatch::create([..., 'sequence' => 1]) would silently
     * drop it and the round.edit view's venue/sequence=1 lookup would never
     * match.
     */
    private function createMatch(Association $association, Series $series, Division $division, $schedule, Round $round, Venue $venue, string $name): PLMatch
    {
        $match = new PLMatch(['name' => $name]);
        $match->association_id = $association->id;
        $match->series_id = $series->id;
        $match->division_id = $division->id;
        $match->schedule_id = $schedule->id;
        $match->round_id = $round->id;
        $match->venue_id = $venue->id;
        $match->sequence = 1;
        $match->save();

        return $match;
    }
}
