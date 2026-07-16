<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\Team;
use App\User;
use App\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticScheduleGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(): array
    {
        $association = Association::factory()->create(['subdomain' => 'auto-gen']);

        $division = new Division(['name' => 'Auto Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Auto Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Auto Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'weekday' => 'mon',
        ]);

        // 6 active teams + 2 inactive (must never be scheduled).
        $activeTeams = collect(range(1, 6))->map(
            fn ($i) => Team::create(['name' => "Team {$i}", 'association_id' => $association->id, 'active' => true])
        );
        Team::create(['name' => 'Inactive Team A', 'association_id' => $association->id, 'active' => false]);
        Team::create(['name' => 'Inactive Team B', 'association_id' => $association->id, 'active' => false]);

        // 2 active venues + 1 inactive (must never receive a match).
        $activeVenues = collect(range(1, 2))->map(
            fn ($i) => Venue::create(['name' => "Venue {$i}", 'association_id' => $association->id, 'active' => true])
        );
        $inactiveVenue = Venue::create(['name' => 'Inactive Venue', 'association_id' => $association->id, 'active' => false]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        return compact('association', 'schedule', 'activeTeams', 'activeVenues', 'inactiveVenue');
    }

    public function test_generating_automatically_does_not_persist_until_accepted()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture();

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        $response->assertRedirect(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());

        $review = $this->get(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));
        $review->assertStatus(200);
        $review->assertSee('Accept');
        $review->assertSee('Discard');
    }

    public function test_accepting_persists_a_fully_valid_schedule()
    {
        ['association' => $association, 'schedule' => $schedule, 'inactiveVenue' => $inactiveVenue] = $this->buildFixture();

        $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        $response = $this->post(route('schedule.generate-matches.accept', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        // July-Sept 2026 has 13 Mondays.
        $rounds = Round::where('schedule_id', $schedule->id)->orderBy('start_date')->get();
        $this->assertSame(13, $rounds->count());

        $matches = PLMatch::where('schedule_id', $schedule->id)->get();
        $this->assertGreaterThan(0, $matches->count());

        // Hard constraints, verified directly against the persisted data:
        $inactiveTeamIds = \App\Team::where('association_id', $association->id)->where('active', false)->pluck('id');

        foreach ($matches as $match) {
            $this->assertNotContains($match->home_team_id, $inactiveTeamIds);
            $this->assertNotContains($match->away_team_id, $inactiveTeamIds);
            $this->assertNotSame($inactiveVenue->id, $match->venue_id);
        }

        // No back-to-back opponent repeats and no team double-booked per round.
        $lastOpponent = [];
        foreach ($rounds as $round) {
            $roundMatches = $matches->where('round_id', $round->id);
            $seen = [];

            foreach ($roundMatches as $match) {
                foreach ([$match->home_team_id, $match->away_team_id] as $teamId) {
                    $this->assertArrayNotHasKey($teamId, $seen, "team {$teamId} double-booked in round {$round->id}");
                    $seen[$teamId] = true;
                }

                $this->assertNotSame($lastOpponent[$match->home_team_id] ?? null, $match->away_team_id);
                $this->assertNotSame($lastOpponent[$match->away_team_id] ?? null, $match->home_team_id);
            }

            $newLastOpponent = [];
            foreach ($roundMatches as $match) {
                $newLastOpponent[$match->home_team_id] = $match->away_team_id;
                $newLastOpponent[$match->away_team_id] = $match->home_team_id;
            }
            $lastOpponent = $newLastOpponent;
        }

        // Matches played spread across active teams should be minimal (equal or off by one).
        $counts = [];
        foreach ($matches as $match) {
            $counts[$match->home_team_id] = ($counts[$match->home_team_id] ?? 0) + 1;
            $counts[$match->away_team_id] = ($counts[$match->away_team_id] ?? 0) + 1;
        }
        $this->assertLessThanOrEqual(1, max($counts) - min($counts));

        // The session candidate is cleared after accept.
        $this->assertNull(session("schedule_generation.{$schedule->id}.candidate"));
    }

    public function test_discard_and_retry_regenerates_without_persisting()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture();

        $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        $response = $this->post(route('schedule.generate-matches.retry', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());
        $this->assertNotNull(session("schedule_generation.{$schedule->id}.candidate"));
    }

    public function test_review_screen_redirects_back_if_no_candidate_is_in_session()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture();

        $response = $this->get(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));
    }

    public function test_a_team_is_never_persisted_as_away_at_their_own_home_venue()
    {
        ['association' => $association, 'schedule' => $schedule, 'activeTeams' => $activeTeams, 'activeVenues' => $activeVenues] = $this->buildFixture();

        // Mirrors real data (e.g. "Rullo's Team"): teams 1 and 2 both call
        // Venue 1 home; the rest have no home venue on file.
        $activeTeams[0]->update(['venue_id' => $activeVenues[0]->id]);
        $activeTeams[1]->update(['venue_id' => $activeVenues[0]->id]);
        $activeTeams[2]->update(['venue_id' => $activeVenues[1]->id]);

        $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);
        $this->post(route('schedule.generate-matches.accept', ['association' => $association, 'schedule' => $schedule]));

        $homeVenueIdByTeam = Team::where('association_id', $association->id)->pluck('venue_id', 'id');

        foreach (PLMatch::where('schedule_id', $schedule->id)->get() as $match) {
            $this->assertNotSame(
                $homeVenueIdByTeam[$match->away_team_id] ?? null,
                $match->venue_id,
                "team {$match->away_team_id} was persisted away at their own home venue"
            );
        }
    }
}
