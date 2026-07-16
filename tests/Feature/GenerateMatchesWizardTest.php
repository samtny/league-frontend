<?php

namespace Tests\Feature;

use App\Association;
use App\Division;
use App\PLMatch;
use App\Round;
use App\Series;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Generate Matches" wizard is reachable from
 * /admin/association/{a}/schedule/{s}: if any of the schedule's Matches
 * already have a Home/Away team assigned, a warning gate is shown first
 * (Proceed/Cancel, nothing mutated) since both options on the next step end
 * up clearing them; otherwise it goes straight to the select screen (Clear /
 * Automatic radios). Neither option creates or deletes Round/Match rows -
 * Clear just nulls out home_team_id/away_team_id, and Automatic (covered in
 * AutomaticScheduleGenerationTest) only ever populates those same fields on
 * Matches that already exist.
 */
class GenerateMatchesWizardTest extends TestCase
{
    use RefreshDatabase;

    private function buildFixture(string $subdomain, ?string $weekday = 'mon'): array
    {
        $association = Association::factory()->create(['subdomain' => $subdomain]);

        $division = new Division(['name' => 'Wizard Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Wizard Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Wizard Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'weekday' => $weekday,
        ]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        return compact('association', 'schedule');
    }

    private function createRound($schedule): Round
    {
        $round = new Round(['name' => 'Round 1']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = now();
        $round->end_date = now();
        $round->save();

        return $round;
    }

    private function createMatch($association, $schedule, $round, $homeTeamId = null, $awayTeamId = null): PLMatch
    {
        $match = new PLMatch(['name' => 'Match 1']);
        $match->association_id = $association->id;
        $match->series_id = $schedule->series_id;
        $match->division_id = $schedule->division_id;
        $match->schedule_id = $schedule->id;
        $match->round_id = $round->id;
        $match->start_date = $round->start_date;
        $match->end_date = $round->end_date;
        $match->home_team_id = $homeTeamId;
        $match->away_team_id = $awayTeamId;
        $match->save();

        return $match;
    }

    public function test_no_existing_rounds_goes_straight_to_the_select_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-a');

        $response = $this->get(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Assignment Method');
    }

    public function test_existing_rounds_without_assigned_matches_go_straight_to_the_select_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-b0');

        $round = $this->createRound($schedule);
        $this->createMatch($association, $schedule, $round);

        $response = $this->get(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Assignment Method');
    }

    public function test_confirm_gate_is_shown_when_matches_already_have_assigned_teams()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-b');

        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'active' => true]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'active' => true]);

        $round = $this->createRound($schedule);
        $match = $this->createMatch($association, $schedule, $round, $homeTeam->id, $awayTeam->id);

        $response = $this->get(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('clear those assignments');
        $response->assertSee('Proceed');
        $response->assertDontSee('Assignment Method');

        // Purely a warning - nothing was mutated just by viewing it.
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'home_team_id' => $homeTeam->id, 'away_team_id' => $awayTeam->id]);

        // "Proceed" leads to the actual select screen.
        $proceed = $this->get(route('schedule.generate-matches.select', ['association' => $association, 'schedule' => $schedule]));
        $proceed->assertStatus(200);
        $proceed->assertSee('Assignment Method');
    }

    public function test_confirm_gate_cancel_link_returns_to_the_schedule()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-b1');

        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'active' => true]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'active' => true]);

        $round = $this->createRound($schedule);
        $this->createMatch($association, $schedule, $round, $homeTeam->id, $awayTeam->id);

        $response = $this->get(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));

        $response->assertSee(route('schedule.view', ['association' => $association, 'schedule' => $schedule]), false);
    }

    public function test_clearing_nulls_team_assignments_without_deleting_rounds_or_matches()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-c');

        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'active' => true]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'active' => true]);

        $round = $this->createRound($schedule);
        $match = $this->createMatch($association, $schedule, $round, $homeTeam->id, $awayTeam->id);

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'clear',
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        // Round and Match rows are left in place - only the assignments are cleared.
        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'home_team_id' => null, 'away_team_id' => null]);
    }

    /**
     * Regression guard: clearing assignments must be scoped to the target
     * schedule's own Matches - a naive query missing the schedule_id filter
     * would clear every schedule's assignments at once.
     */
    public function test_clearing_only_affects_the_current_schedules_matches_and_leaves_other_data_untouched()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-isolation-a');
        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'active' => true]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'active' => true]);
        $round = $this->createRound($schedule);
        $match = $this->createMatch($association, $schedule, $round, $homeTeam->id, $awayTeam->id);

        // A second, unrelated schedule (different association entirely) with
        // its own assigned Round/Match, plus an unrelated Team/Division/Series.
        $otherAssociation = Association::factory()->create(['subdomain' => 'wizard-isolation-b']);
        $otherDivision = new Division(['name' => 'Other Division']);
        $otherDivision->association_id = $otherAssociation->id;
        $otherDivision->save();
        $otherSeries = Series::create(['name' => 'Other Series', 'association_id' => $otherAssociation->id]);
        $otherSchedule = $otherAssociation->schedules()->create([
            'name' => 'Other Schedule', 'series_id' => $otherSeries->id, 'division_id' => $otherDivision->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'weekday' => 'tue',
        ]);
        $otherHomeTeam = Team::create(['name' => 'Other Home Team', 'association_id' => $otherAssociation->id, 'active' => true]);
        $otherAwayTeam = Team::create(['name' => 'Other Away Team', 'association_id' => $otherAssociation->id, 'active' => true]);
        $otherRound = $this->createRound($otherSchedule);
        $otherMatch = $this->createMatch($otherAssociation, $otherSchedule, $otherRound, $otherHomeTeam->id, $otherAwayTeam->id);

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'clear',
        ]);

        $response->assertRedirect(route('schedule.view', ['association' => $association, 'schedule' => $schedule]));

        // Target schedule's assignment was cleared.
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'home_team_id' => null, 'away_team_id' => null]);

        // Everything belonging to the other schedule is untouched.
        $this->assertDatabaseHas('rounds', ['id' => $otherRound->id]);
        $this->assertDatabaseHas('matches', [
            'id' => $otherMatch->id,
            'home_team_id' => $otherHomeTeam->id,
            'away_team_id' => $otherAwayTeam->id,
        ]);
        $this->assertDatabaseHas('teams', ['id' => $otherHomeTeam->id, 'name' => 'Other Home Team']);
        $this->assertDatabaseHas('teams', ['id' => $otherAwayTeam->id, 'name' => 'Other Away Team']);
        $this->assertDatabaseHas('schedules', ['id' => $otherSchedule->id, 'name' => 'Other Schedule']);

        // The target schedule's own Round/Match rows also weren't deleted.
        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
        $this->assertDatabaseHas('matches', ['id' => $match->id]);
    }

    public function test_selecting_automatic_assignment_leaves_existing_rounds_untouched_and_goes_to_review()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-e');

        $round = $this->createRound($schedule);

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
        ]);

        // Nothing is persisted until the review screen's Accept is submitted -
        // the existing Round is left exactly as it was either way.
        $response->assertRedirect(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));
        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
        $this->assertSame(1, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_generate_requires_a_valid_method()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-f');

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'bogus',
        ]);

        $response->assertSessionHasErrors('generate');
        $this->assertSame(0, Round::where('schedule_id', $schedule->id)->count());
    }

    public function test_missing_weekday_shows_invalid_state_instead_of_select_step()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-h', null);

        $response = $this->get(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Match Weekday');
        $response->assertDontSee('Assignment Method');
    }

    public function test_missing_weekday_blocks_clearing_assignments_even_if_posted_directly()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-i', null);

        $homeTeam = Team::create(['name' => 'Home Team', 'association_id' => $association->id, 'active' => true]);
        $awayTeam = Team::create(['name' => 'Away Team', 'association_id' => $association->id, 'active' => true]);

        $round = $this->createRound($schedule);
        $match = $this->createMatch($association, $schedule, $round, $homeTeam->id, $awayTeam->id);

        $response = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'clear',
        ]);

        $response->assertRedirect(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));
        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
    }

    public function test_missing_start_or_end_date_also_blocks_generation()
    {
        $association = Association::factory()->create(['subdomain' => 'wizard-k']);

        $division = new Division(['name' => 'Wizard Division']);
        $division->association_id = $association->id;
        $division->save();

        $series = Series::create(['name' => 'Wizard Series', 'association_id' => $association->id]);
        $schedule = $association->schedules()->create([
            'name' => 'Wizard Schedule', 'series_id' => $series->id, 'division_id' => $division->id,
            'start_date' => null, 'end_date' => null, 'weekday' => 'mon',
        ]);

        \Bouncer::allow('superadmin')->everything();
        $admin = User::factory()->create();
        \Bouncer::assign('superadmin')->to($admin);
        $this->actingAs($admin);

        $response = $this->get(route('schedule.generate-matches', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Start Date');
        $response->assertSee('End Date');
        $response->assertDontSee('Assignment Method');
    }
}
