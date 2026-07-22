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

/**
 * Automatic assignment no longer creates or deletes Rounds/Matches - it only
 * populates home_team_id/away_team_id onto Matches that already exist for
 * the schedule (see ScheduleController::applyCandidateToExistingMatches()).
 * These fixtures build that pre-existing structure the same way production
 * does: posting to the ordinary Schedule update endpoint with no rounds yet
 * present triggers ScheduleController::regenerateRounds(), which creates one
 * empty (no team assigned) Round/Match per active venue per round date -
 * exactly what a real schedule looks like before anyone runs Automatic.
 */
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
            fn ($i) => Team::create(['name' => "Team {$i}", 'association_id' => $association->id, 'active' => true, 'division_id' => $division->id])
        );
        Team::create(['name' => 'Inactive Team A', 'association_id' => $association->id, 'active' => false, 'division_id' => $division->id]);
        Team::create(['name' => 'Inactive Team B', 'association_id' => $association->id, 'active' => false, 'division_id' => $division->id]);

        // 2 active venues + 1 inactive (must never receive a match).
        $activeVenues = collect(range(1, 2))->map(
            fn ($i) => Venue::create(['name' => "Venue {$i}", 'association_id' => $association->id, 'active' => true])
        );
        $inactiveVenue = Venue::create(['name' => 'Inactive Venue', 'association_id' => $association->id, 'active' => false]);

        $activeVenues->each(fn ($venue) => $venue->divisions()->attach($division->id));
        $inactiveVenue->divisions()->attach($division->id);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        // Creates the empty Rounds/Matches (no Rounds exist yet, so this
        // resubmission of the schedule's own values triggers regeneration).
        $this->post(route('schedule.update', ['association' => $association, 'schedule' => $schedule]), [
            'name' => $schedule->name,
            'division_id' => $schedule->division_id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-30',
            'weekday' => 'mon',
        ]);

        return compact('association', 'schedule', 'activeTeams', 'activeVenues', 'inactiveVenue');
    }

    public function test_generating_automatically_does_not_persist_until_accepted()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture();

        // July-Sept 2026 has 13 Mondays.
        $this->assertSame(13, Round::where('schedule_id', $schedule->id)->count());

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        $response->assertRedirect(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));

        // Rounds/Matches are untouched - nothing is mutated until Accept.
        $this->assertSame(13, Round::where('schedule_id', $schedule->id)->count());
        $this->assertSame(0, PLMatch::where('schedule_id', $schedule->id)->whereNotNull('home_team_id')->count());

        $review = $this->get(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));
        $review->assertStatus(200);
        $review->assertSee('Accept');
        $review->assertSee('Discard');
    }

    public function test_off_week_and_playoffs_week_rounds_are_excluded_from_generation()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture();

        $rounds = Round::where('schedule_id', $schedule->id)->orderBy('start_date')->get();
        $offWeekRound = $rounds[2];
        $offWeekRound->update(['off_week' => true]);
        $playoffsRound = $rounds[5];
        $playoffsRound->update(['playoffs_week' => true]);

        $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        $candidateData = session("schedule_generation.{$schedule->id}.candidate");
        $this->assertNotNull($candidateData);

        $roundIdsInCandidate = collect($candidateData['rounds'])->pluck('round_id');

        $this->assertNotContains($offWeekRound->id, $roundIdsInCandidate);
        $this->assertNotContains($playoffsRound->id, $roundIdsInCandidate);

        // Every OTHER round is still present - this isn't merely "the
        // flagged rounds are missing," it's specifically only those two.
        $expectedIncludedIds = $rounds->pluck('id')->diff([$offWeekRound->id, $playoffsRound->id])->sort()->values();
        $this->assertEqualsCanonicalizing($expectedIncludedIds->all(), $roundIdsInCandidate->all());
    }

    public function test_accepting_persists_a_fully_valid_schedule()
    {
        ['association' => $association, 'schedule' => $schedule, 'inactiveVenue' => $inactiveVenue] = $this->buildFixture();

        $roundIdsBefore = Round::where('schedule_id', $schedule->id)->orderBy('id')->pluck('id')->all();
        $matchIdsBefore = PLMatch::where('schedule_id', $schedule->id)->orderBy('id')->pluck('id')->all();

        $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        $response = $this->post(route('schedule.generate-matches.accept', ['association' => $association, 'schedule' => $schedule]));

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        // Same Round/Match rows as before - Accept only ever updates
        // home_team_id/away_team_id, never deletes or recreates rows.
        $rounds = Round::where('schedule_id', $schedule->id)->orderBy('start_date')->get();
        $this->assertSame($roundIdsBefore, $rounds->pluck('id')->sort()->values()->all());

        $matches = PLMatch::where('schedule_id', $schedule->id)->get();
        $this->assertSame($matchIdsBefore, $matches->pluck('id')->sort()->values()->all());
        $this->assertGreaterThan(0, $matches->whereNotNull('home_team_id')->count());

        // Hard constraints, verified directly against the persisted data:
        $inactiveTeamIds = \App\Team::where('association_id', $association->id)->where('active', false)->pluck('id');

        foreach ($matches as $match) {
            $this->assertNotContains($match->home_team_id, $inactiveTeamIds);
            $this->assertNotContains($match->away_team_id, $inactiveTeamIds);
            $this->assertNotSame($inactiveVenue->id, $match->venue_id);
        }

        // No team double-booked per round. (Repeating the same opponent in
        // consecutive rounds is a soft criterion, not a hard constraint, so
        // it's no longer asserted here - see ScheduleScorerTest for that.)
        foreach ($rounds as $round) {
            $roundMatches = $matches->where('round_id', $round->id)->whereNotNull('home_team_id');
            $seen = [];

            foreach ($roundMatches as $match) {
                foreach ([$match->home_team_id, $match->away_team_id] as $teamId) {
                    $this->assertArrayNotHasKey($teamId, $seen, "team {$teamId} double-booked in round {$round->id}");
                    $seen[$teamId] = true;
                }
            }
        }

        // Matches-played balance across teams isn't asserted here: it's
        // governed by the equal_matches_played soft criterion, which is
        // currently disabled in config/schedule_generation.php (see its
        // soft_criteria TODO) - with it off, nothing in generation targets
        // this balance, so it's incidental rather than guaranteed. See
        // ScheduleGeneratorTest for coverage of that criterion in isolation,
        // with it explicitly enabled.

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
        $this->assertSame(13, Round::where('schedule_id', $schedule->id)->count());
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

        foreach (PLMatch::where('schedule_id', $schedule->id)->whereNotNull('home_team_id')->get() as $match) {
            $this->assertNotSame(
                $homeVenueIdByTeam[$match->away_team_id] ?? null,
                $match->venue_id,
                "team {$match->away_team_id} was persisted away at their own home venue"
            );
        }
    }

    /**
     * Automatic runs an implicit Clear immediately before applying its
     * candidate (see ScheduleController::generateMatchesAccept()), precisely
     * because applyCandidateToExistingMatches() only ever writes team ids
     * for Matches the candidate actually touches - without that Clear, a
     * Match slot the candidate skips would keep whatever was assigned to it
     * by a previous run.
     */
    public function test_accepting_clears_stale_assignments_on_matches_the_new_candidate_does_not_touch()
    {
        $association = Association::factory()->create(['subdomain' => 'auto-gen-stale']);

        $division = new Division(['name' => 'Auto Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Auto Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Auto Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-06', 'end_date' => '2026-07-06', 'weekday' => 'mon',
        ]);

        // 2 active teams but 2 active venues: capacity is min(1, 2) = 1, so
        // only one of the round's two Match rows is ever touched by a
        // candidate - the other is a stale leftover from a previous run.
        $teamA = Team::create(['name' => 'Team A', 'association_id' => $association->id, 'active' => true, 'division_id' => $division->id]);
        $teamB = Team::create(['name' => 'Team B', 'association_id' => $association->id, 'active' => true, 'division_id' => $division->id]);
        $venue1 = Venue::create(['name' => 'Venue 1', 'association_id' => $association->id, 'active' => true]);
        $venue2 = Venue::create(['name' => 'Venue 2', 'association_id' => $association->id, 'active' => true]);
        $venue1->divisions()->attach($division->id);
        $venue2->divisions()->attach($division->id);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        $this->post(route('schedule.update', ['association' => $association, 'schedule' => $schedule]), [
            'name' => $schedule->name,
            'division_id' => $schedule->division_id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-06',
            'weekday' => 'mon',
        ]);

        $round = Round::where('schedule_id', $schedule->id)->firstOrFail();
        $matches = PLMatch::where('round_id', $round->id)->get();
        $this->assertSame(2, $matches->count());

        // Simulate a stale assignment left over from a previous run on both rows.
        foreach ($matches as $match) {
            $match->update(['home_team_id' => $teamA->id, 'away_team_id' => $teamB->id]);
        }

        $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);
        $this->post(route('schedule.generate-matches.accept', ['association' => $association, 'schedule' => $schedule]));

        $refreshed = PLMatch::where('round_id', $round->id)->get();

        // Exactly one match got the new assignment; the other was cleared,
        // not left with its stale home/away teams from before Accept ran.
        $this->assertSame(1, $refreshed->whereNotNull('home_team_id')->count());

        $untouched = $refreshed->whereNull('home_team_id')->first();
        $this->assertNotNull($untouched);
        $this->assertNull($untouched->away_team_id);
    }
}
