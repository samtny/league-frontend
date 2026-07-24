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

    private function createRoundOnDate($schedule, $date): Round
    {
        $round = new Round(['name' => 'Round']);
        $round->schedule_id = $schedule->id;
        $round->series_id = $schedule->series_id;
        $round->division_id = $schedule->division_id;
        $round->start_date = $date;
        $round->end_date = $date;
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

    /**
     * See plan.md "Size-Aware Schedule Generation" §5/§7: the select screen
     * offers every GenerationStrategy option (never hides one, decision
     * 2.6), with the engine's regime-based recommendation pre-checked. 8
     * teams each owning a distinct venue, over exactly 7 rounds (R = N-1),
     * is the single-cycle regime plan.md §1c says recommends SeedOnly - more
     * than 6 teams so the newer N <= 6 -> Exact rule (plan.md §5 Phase 4b,
     * covered by test_select_screen_recommends_exact_for_a_small_eligible_league()
     * below) doesn't take priority over it.
     */
    public function test_select_screen_renders_every_strategy_with_the_recommended_one_pre_checked()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-strategy-a');

        $venues = collect(range(1, 8))->map(
            fn ($i) => Venue::create(['name' => "Venue {$i}", 'association_id' => $association->id, 'active' => true])
        );
        $venues->each(fn ($venue) => $venue->divisions()->attach($schedule->division_id));

        collect(range(1, 8))->each(fn ($i) => Team::create([
            'name' => "Team {$i}",
            'association_id' => $association->id,
            'active' => true,
            'division_id' => $schedule->division_id,
            'venue_id' => $venues[$i - 1]->id,
        ]));

        foreach (range(0, 6) as $i) {
            $this->createRoundOnDate($schedule, now()->addWeeks($i));
        }

        $response = $this->get(route('schedule.generate-matches.select', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('Seed only');
        $response->assertSee('Seed + annealing');
        $response->assertSee('Greedy');
        $response->assertSee('Exact');

        $response->assertSee('id="strategy_seed_only" name="strategy" value="seed_only" checked', false);
        $response->assertDontSee('id="strategy_seed_and_anneal" name="strategy" value="seed_and_anneal" checked', false);
        $response->assertDontSee('id="strategy_greedy" name="strategy" value="greedy" checked', false);
        $response->assertDontSee('id="strategy_exact" name="strategy" value="exact" checked', false);
    }

    /**
     * plan.md §5 Phase 4b's size-first pre-selection rule: 6 or fewer
     * active teams with an eligible venue structure recommends Exact,
     * taking priority over what the regime rule alone would pick (a
     * single-cycle season here would otherwise recommend SeedOnly, per the
     * test right above).
     */
    public function test_select_screen_recommends_exact_for_a_small_eligible_league()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-strategy-exact');

        $venues = collect(range(1, 4))->map(
            fn ($i) => Venue::create(['name' => "Venue {$i}", 'association_id' => $association->id, 'active' => true])
        );
        $venues->each(fn ($venue) => $venue->divisions()->attach($schedule->division_id));

        collect(range(1, 4))->each(fn ($i) => Team::create([
            'name' => "Team {$i}",
            'association_id' => $association->id,
            'active' => true,
            'division_id' => $schedule->division_id,
            'venue_id' => $venues[$i - 1]->id,
        ]));

        $this->createRoundOnDate($schedule, '2026-07-06');
        $this->createRoundOnDate($schedule, '2026-07-13');
        $this->createRoundOnDate($schedule, '2026-07-20');

        $response = $this->get(route('schedule.generate-matches.select', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('id="strategy_exact" name="strategy" value="exact" checked', false);
        $response->assertDontSee('id="strategy_seed_only" name="strategy" value="seed_only" checked', false);
    }

    /**
     * The inverse shape: no team owns a home venue at all, so
     * RoundRobinConstructor::isEligible() is false and Greedy is the only
     * strategy that can actually run - plan.md §5's eligibility-first rule.
     * Still every option is offered (decision 2.6), just not pre-checked.
     */
    public function test_select_screen_recommends_greedy_when_venue_ownership_data_is_missing()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-strategy-b');

        collect(range(1, 4))->each(fn ($i) => Team::create([
            'name' => "Team {$i}",
            'association_id' => $association->id,
            'active' => true,
            'division_id' => $schedule->division_id,
        ]));

        $this->createRoundOnDate($schedule, '2026-07-06');

        $response = $this->get(route('schedule.generate-matches.select', ['association' => $association, 'schedule' => $schedule]));

        $response->assertStatus(200);
        $response->assertSee('id="strategy_greedy" name="strategy" value="greedy" checked', false);
        $response->assertDontSee('id="strategy_seed_only" name="strategy" value="seed_only" checked', false);
    }

    /**
     * generateMatchesRetry() must reuse the strategy generateMatchesStore()
     * was posted with, not silently fall back to the recommendation - the
     * strategy has to survive in the session alongside the candidate/report
     * (see ScheduleController::sessionKey()/generateMatchesStore()).
     */
    public function test_a_posted_strategy_survives_a_retry()
    {
        ['association' => $association, 'schedule' => $schedule] = $this->buildFixture('wizard-strategy-c');

        $venues = collect(range(1, 4))->map(
            fn ($i) => Venue::create(['name' => "Venue {$i}", 'association_id' => $association->id, 'active' => true])
        );
        $venues->each(fn ($venue) => $venue->divisions()->attach($schedule->division_id));

        collect(range(1, 4))->each(fn ($i) => Team::create([
            'name' => "Team {$i}",
            'association_id' => $association->id,
            'active' => true,
            'division_id' => $schedule->division_id,
            'venue_id' => $venues[$i - 1]->id,
        ]));

        // Only 4 active teams, so the recommendation would be Exact (plan.md
        // §5 Phase 4b's N <= 6 rule) regardless of the round count -
        // deliberately posting the NON-recommended 'greedy' strategy
        // instead, so a silent revert-to-recommendation on retry would be
        // caught by this test.
        for ($i = 0; $i < 7; $i++) {
            $this->createRoundOnDate($schedule, now()->addWeeks($i));
        }

        $store = $this->post(route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]), [
            'generate' => 'random',
            'strategy' => 'greedy',
        ]);
        $store->assertRedirect(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));

        $firstReport = session("schedule_generation.{$schedule->id}.report");
        $this->assertSame('greedy', $firstReport['strategy']);

        $retry = $this->post(route('schedule.generate-matches.retry', ['association' => $association, 'schedule' => $schedule]));
        $retry->assertRedirect(route('schedule.generate-matches.review', ['association' => $association, 'schedule' => $schedule]));

        $retryReport = session("schedule_generation.{$schedule->id}.report");
        $this->assertSame('greedy', $retryReport['strategy']);
    }
}
