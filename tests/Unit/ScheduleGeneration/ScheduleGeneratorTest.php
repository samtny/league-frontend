<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\ExactSolver;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\GenerationStrategy;
use App\Services\ScheduleGeneration\InitialSolutionBuilder;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\RoundRobinConstructor;
use App\Services\ScheduleGeneration\ScheduleGenerator;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class ScheduleGeneratorTest extends TestCase
{
    private function teams(int ...$ids): array
    {
        return array_map(fn (int $id) => new TeamInput($id, "Team {$id}"), $ids);
    }

    /**
     * @param  array<int, int|null>  $homeVenueIdByTeamId
     */
    private function teamsWithHomeVenues(array $homeVenueIdByTeamId): array
    {
        return array_map(
            fn (int $id, ?int $venueId) => new TeamInput($id, "Team {$id}", $venueId),
            array_keys($homeVenueIdByTeamId),
            array_values($homeVenueIdByTeamId),
        );
    }

    private function venues(int ...$ids): array
    {
        return array_map(fn (int $id) => new VenueInput($id, "Venue {$id}"), $ids);
    }

    /**
     * @param  VenueInput[]  $venues
     * @return RoundInput[]
     */
    private function rounds(int $count, array $venues): array
    {
        $rounds = [];
        $date = new \DateTimeImmutable('2026-07-06');
        $matchId = 1;

        for ($i = 0; $i < $count; $i++) {
            $slots = array_map(
                fn (VenueInput $venue) => new MatchSlotInput($matchId++, $venue->id, $venue->name),
                $venues,
            );

            $rounds[] = new RoundInput($i + 1, $date, $slots);
            $date = $date->add(new \DateInterval('P7D'));
        }

        return $rounds;
    }

    private function generator(int $seed): ScheduleGenerator
    {
        return new ScheduleGenerator(new SeededRng($seed), new ScheduleScorer);
    }

    public function test_valid_schedule_satisfies_all_hard_constraints_for_a_realistic_league()
    {
        $venues = $this->venues(10, 20);

        $result = $this->generator(1)->generate(
            $this->rounds(10, $venues),
            $this->teams(1, 2, 3, 4, 5, 6),
            $venues,
            new GenerationConfig,
        );

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertSame([], $result->report->hardViolations);
    }

    public function test_same_seed_and_inputs_produce_identical_output()
    {
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(8, $venues);

        // timeBudgetMs is deliberately a wall-clock ceiling in production
        // (see GenerationConfig's docblock), so the default would make the
        // number of iterations completed before it trips depend on real
        // elapsed time - not just the seed - and vary run to run. Pinning it
        // huge here means maxAttempts is the only thing that can end the
        // search, which is what actually stays deterministic for a fixed seed.
        $config = new GenerationConfig(timeBudgetMs: 1_000_000_000);

        $resultA = $this->generator(42)->generate($rounds, $teams, $venues, $config);
        $resultB = $this->generator(42)->generate($rounds, $teams, $venues, $config);

        $this->assertEquals($resultA->candidate, $resultB->candidate);
        $this->assertSame($resultA->attemptsUsed, $resultB->attemptsUsed);
    }

    public function test_odd_team_count_byes_are_evenly_rotated()
    {
        // 5 active teams, 1 venue -> capacity 2 matches/round (4 teams), 1 bye/round.
        $venues = $this->venues(10);

        $result = $this->generator(7)->generate(
            $this->rounds(10, $venues),
            $this->teams(1, 2, 3, 4, 5),
            $venues,
            new GenerationConfig,
        );

        $byeCounts = array_fill_keys([1, 2, 3, 4, 5], 0);

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->byeTeamIds as $teamId) {
                $byeCounts[$teamId]++;
            }
        }

        $this->assertLessThanOrEqual(1, max($byeCounts) - min($byeCounts), 'bye counts should differ by at most 1 across the schedule');
    }

    public function test_inactive_teams_and_venues_can_never_appear_because_they_are_not_in_the_input()
    {
        // Only active teams/venues are ever passed in, so there is nothing for
        // the generator to select from except them - this is what makes H2
        // structural rather than merely scored.
        $activeTeams = $this->teams(1, 2, 3, 4);
        $activeVenues = $this->venues(10, 20);

        $result = $this->generator(3)->generate($this->rounds(6, $activeVenues), $activeTeams, $activeVenues, new GenerationConfig);

        $allowedTeamIds = [1, 2, 3, 4];
        $allowedVenueIds = [10, 20];

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $this->assertContains($match->homeTeamId, $allowedTeamIds);
                $this->assertContains($match->awayTeamId, $allowedTeamIds);
                $this->assertContains($match->venueId, $allowedVenueIds);
            }

            foreach ($round->byeTeamIds as $teamId) {
                $this->assertContains($teamId, $allowedTeamIds);
            }
        }
    }

    public function test_fewer_than_two_active_teams_is_reported_as_degenerate()
    {
        $venues = $this->venues(10);

        $result = $this->generator(1)->generate($this->rounds(4, $venues), $this->teams(1), $venues, new GenerationConfig);

        $this->assertTrue($result->report->degenerate);
        $this->assertNotNull($result->report->degenerateReason);
        // No matches at all should have been scheduled.
        foreach ($result->candidate->rounds as $round) {
            $this->assertSame([], $round->matches);
        }
    }

    public function test_no_active_venues_is_reported_as_degenerate()
    {
        $result = $this->generator(1)->generate($this->rounds(4, []), $this->teams(1, 2), [], new GenerationConfig);

        $this->assertTrue($result->report->degenerate);
        $this->assertNotNull($result->report->degenerateReason);
    }

    public function test_no_round_dates_is_reported_as_degenerate_with_an_empty_schedule()
    {
        $result = $this->generator(1)->generate([], $this->teams(1, 2), $this->venues(10), new GenerationConfig);

        $this->assertTrue($result->report->degenerate);
        $this->assertSame([], $result->candidate->rounds);
    }

    public function test_exactly_two_teams_over_many_rounds_scores_heavily_on_repeat_opponent_but_is_not_degenerate()
    {
        // With only 2 active teams, they are forced to face each other every
        // round they both play - repeating the same opponent in consecutive
        // rounds is a soft criterion now, not a hard rejection, so this
        // commits a valid (if heavily-penalized) schedule rather than being
        // flagged degenerate.
        $config = new GenerationConfig(maxAttempts: 20, timeBudgetMs: 500);
        $venues = $this->venues(10);

        $result = $this->generator(9)->generate($this->rounds(5, $venues), $this->teams(1, 2), $venues, $config);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertArrayHasKey('repeat_opponent_consecutive_rounds', $result->report->softViolationsByCriterion);
    }

    public function test_respects_the_max_attempts_budget()
    {
        $config = new GenerationConfig(maxAttempts: 3, timeBudgetMs: 60_000);
        $venues = $this->venues(10);

        $result = $this->generator(5)->generate($this->rounds(5, $venues), $this->teams(1, 2), $venues, $config);

        $this->assertLessThanOrEqual(3, $result->attemptsUsed);
    }

    public function test_a_team_is_never_away_when_the_match_is_at_their_own_home_venue()
    {
        // Teams 1-4 each own one of the 4 active venues; teams 5-6 have none.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => null, 6 => null]);
        $venues = $this->venues(100, 200, 300, 400);

        $result = $this->generator(11)->generate($this->rounds(12, $venues), $teams, $venues, new GenerationConfig);

        $homeVenueIdByTeam = [1 => 100, 2 => 200, 3 => 300, 4 => 400, 5 => null, 6 => null];

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $this->assertNotSame(
                    $homeVenueIdByTeam[$match->awayTeamId],
                    $match->venueId,
                    "team #{$match->awayTeamId} was away at their own home venue"
                );
            }
        }
    }

    public function test_teams_with_a_home_venue_get_roughly_half_their_matches_at_home()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);

        $result = $this->generator(30)->generate($this->rounds(16, $venues), $teams, $venues, new GenerationConfig);

        $homeVenueIdByTeam = [1 => 100, 2 => 200, 3 => 300, 4 => 400];
        $homeAppearances = array_fill_keys(array_keys($homeVenueIdByTeam), 0);
        $matchesPlayed = array_fill_keys(array_keys($homeVenueIdByTeam), 0);

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
                    if (isset($matchesPlayed[$teamId])) {
                        $matchesPlayed[$teamId]++;
                    }
                }

                if ($match->venueId === ($homeVenueIdByTeam[$match->homeTeamId] ?? null)) {
                    $homeAppearances[$match->homeTeamId]++;
                }
            }
        }

        foreach ($homeVenueIdByTeam as $teamId => $venueId) {
            $ratio = $homeAppearances[$teamId] / max(1, $matchesPlayed[$teamId]);
            $this->assertGreaterThan(0.25, $ratio, "team #{$teamId} played at home too rarely ({$homeAppearances[$teamId]}/{$matchesPlayed[$teamId]})");
            $this->assertLessThan(0.75, $ratio, "team #{$teamId} played at home too often ({$homeAppearances[$teamId]}/{$matchesPlayed[$teamId]})");
        }
    }

    public function test_two_teams_sharing_a_home_venue_still_produce_a_valid_schedule()
    {
        // Mirrors real data: two active teams can point at the same venue_id.
        // Uses 6 teams (not just the 2 sharing a venue) so the no-repeat-
        // opponent search has enough room to always succeed - a bare 4-team
        // pool is tight enough that the greedy pairing occasionally exhausts
        // its attempt budget regardless of the home-venue feature.
        $teams = $this->teamsWithHomeVenues([1 => 500, 2 => 500, 3 => null, 4 => null, 5 => null, 6 => null]);
        $venues = $this->venues(500, 600);

        $result = $this->generator(33)->generate($this->rounds(10, $venues), $teams, $venues, new GenerationConfig);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
    }

    public function test_a_shared_venue_team_is_never_persisted_away_at_it_even_when_the_only_other_venue_is_exclusively_owned()
    {
        // Regression for a real bug: teams 1 and 2 share venue 1, team 3
        // exclusively owns venue 2 (the only other venue) - when 1 and 2 are
        // paired against each other, RoundBuilder's own collision-avoidance
        // MUST route their match away from venue 1 (either would violate H4
        // by being "away" at their own shared venue), but if the greedy pass
        // resolves an unrelated pair FIRST and that pair happens to grab
        // venue 2 (leaving only venue 1, the forbidden one), team 1/2's match
        // has nowhere safe left to go. Checked across many seeds - this
        // isn't a single-seed fluke, it reproduced on every seed before the
        // processing-order fix in RoundBuilder::assignVenuesAndSides().
        $teams = $this->teamsWithHomeVenues([1 => 1, 2 => 1, 3 => 2, 4 => null, 5 => null, 6 => null]);
        $venues = $this->venues(1, 2);
        $rounds = $this->rounds(13, $venues);
        $homeVenueIdByTeam = [1 => 1, 2 => 1, 3 => 2, 4 => null, 5 => null, 6 => null];

        for ($seed = 1; $seed <= 50; $seed++) {
            $result = $this->generator($seed)->generate($rounds, $teams, $venues, new GenerationConfig(maxAttempts: 50, timeBudgetMs: 30));

            foreach ($result->candidate->rounds as $round) {
                foreach ($round->matches as $match) {
                    $this->assertNotSame(
                        $homeVenueIdByTeam[$match->awayTeamId],
                        $match->venueId,
                        "seed {$seed}: team #{$match->awayTeamId} was away at their own home venue"
                    );
                }
            }
        }
    }

    public function test_home_team_is_never_assigned_to_a_different_teams_exclusive_venue_when_every_team_owns_one()
    {
        // Regression for a real production bug: with every active team
        // owning a distinct venue (RoundRobinConstructor's seed is always
        // perfect here - see test_exclusive_home_venue_seed_is_used_when_it_
        // reaches_a_perfect_score), the corruption came entirely from the
        // polish phase's local moves (venueSwap/opponentRecombine in
        // SimulatedAnnealingOptimizer, which relocate a match's venue or
        // home team without regard for who owns what) - only a hard
        // constraint, not a soft one, reliably stops it, since a single
        // relocation might not even move the needle on the aggregate
        // home_venue_balance score. A short time budget still lets many
        // attempts run (this scale is small), enough to exercise those
        // moves across many seeds.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(10, $venues);
        $config = new GenerationConfig(maxAttempts: 300, timeBudgetMs: 200);

        for ($seed = 1; $seed <= 50; $seed++) {
            $result = $this->generator($seed)->generate($rounds, $teams, $venues, $config);

            $this->assertTrue($result->report->hardConstraintsSatisfied, "seed {$seed}: ".implode(' | ', $result->report->hardViolations));
        }
    }

    public function test_partial_null_venue_input_is_ineligible_for_the_round_robin_seed_so_behavior_is_unchanged()
    {
        // RoundRobinConstructor requires EVERY active team to have a home
        // venue at all (a single shared pair is fine - see
        // RoundRobinConstructorTest and test_single_shared_venue_pair_
        // below - but a team with no venue is not). This exact input -
        // taken from test_two_teams_sharing_a_home_venue_still_produce_a_
        // valid_schedule() above - stays ineligible because of teams 3-6's
        // null venues, not because 1 and 2 share one, so
        // ScheduleGenerator::generate() falls straight through to the
        // unchanged greedy loop exactly as it did before this enhancement.
        $teams = $this->teamsWithHomeVenues([1 => 500, 2 => 500, 3 => null, 4 => null, 5 => null, 6 => null]);
        $venues = $this->venues(500, 600);

        $this->assertFalse((new RoundRobinConstructor(new SeededRng(1)))->isEligible($teams, $venues));
    }

    public function test_single_shared_venue_pair_is_eligible_and_reaches_the_construction_seed()
    {
        // Unlike the fixture above, every team here owns SOME home venue -
        // teams 1 and 2 share venue 500, every other team owns a distinct
        // venue - so this is now eligible for RoundRobinConstructor's seed
        // (see RoundRobinConstructorTest for the underlying mechanics: the
        // shared pair is placed on adjacent slots, which keeps their roles
        // complementary all cycle, and their own head-to-head match is a
        // normal match at the shared venue).
        $teams = $this->teamsWithHomeVenues([1 => 500, 2 => 500, 3 => 600, 4 => 700, 5 => 800, 6 => 900]);
        $venues = $this->venues(500, 600, 700, 800, 900);

        $this->assertTrue((new RoundRobinConstructor(new SeededRng(1)))->isEligible($teams, $venues));

        // Only 2 rounds - short enough that the construction's single-cycle
        // prefix hasn't reached its first unavoidable break yet (mirrors
        // test_exclusive_home_venue_seed_is_used_when_it_reaches_a_perfect_score
        // below), so this is a genuine 0-score seed and should short-circuit.
        $result = $this->generator(1)->generate($this->rounds(2, $venues), $teams, $venues, new GenerationConfig);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied, implode(' | ', $result->report->hardViolations));
        $this->assertSame(0.0, $result->report->score);
        $this->assertSame(0, $result->attemptsUsed, 'a perfect seed should short-circuit before any randomized attempts');
    }

    public function test_exclusive_home_venue_seed_is_used_when_it_reaches_a_perfect_score()
    {
        // 4 teams, 4 distinct owned venues, only 2 rounds - short enough
        // that RoundRobinConstructor's single-cycle prefix hasn't reached
        // its first unavoidable break yet (that happens at round index 2;
        // see RoundRobinConstructorTest and plan.md), so this exact
        // prefix is hard-valid and scores a genuine 0: no repeats, equal
        // matches played, and every team's home/away and home-venue splits
        // are perfectly even. The seed should short-circuit the loop
        // immediately: 0 attempts used.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);

        $result = $this->generator(1)->generate($this->rounds(2, $venues), $teams, $venues, new GenerationConfig);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertSame(0.0, $result->report->score);
        $this->assertSame(0, $result->attemptsUsed, 'a perfect seed should short-circuit before any randomized attempts');
    }

    public function test_exclusive_home_venue_seed_never_regresses_the_associaton_2_schedule_6_benchmark()
    {
        // The real shape that motivated RoundRobinConstructor: 4 teams, 4
        // distinct owned venues, 7 rounds (two full cycles plus a 1-round
        // leftover) - a shape where the construction's pass-boundary seam
        // cost can make the seed itself score worse than a lucky greedy
        // pass at this small scale (see plan.md). The actual guarantee
        // "seed + polish" makes isn't "the seed always wins," it's "the
        // full generator never ends up worse than a single greedy pass
        // would" - asserted here directly by comparing against a fresh
        // single greedy pass over the exact same input, rather than pinning
        // a raw score to the current weight scheme (which is orders of
        // magnitude different from the flat weight=1.0 scheme this bound
        // was originally measured under).
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(7, $venues);
        $config = new GenerationConfig;

        $greedyOnly = (new InitialSolutionBuilder(new SeededRng(1)))->greedyPass($rounds, $teams);
        $greedyReport = (new ScheduleScorer)->score($greedyOnly, $teams, $venues, $config);

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertLessThanOrEqual($greedyReport->score, $result->report->score);
    }

    public function test_exclusive_home_venue_seed_beats_greedy_once_team_count_is_large_enough()
    {
        // Single-cycle schedules (R = N-1) are where the construction's
        // advantage is real and grows with scale: it's a closed-form,
        // break-minimal answer with whole-schedule visibility, while a
        // single greedy pass has none. Compares the construction seed's own
        // score directly against a single greedy pass over the exact same
        // input (rather than pinning an absolute score, which depends on
        // the weight scheme), and confirms the full generator (seed+polish)
        // never ends up worse than the seed alone.
        $homeVenueIdByTeamId = [];
        for ($id = 1; $id <= 16; $id++) {
            $homeVenueIdByTeamId[$id] = 1000 + $id;
        }

        $teams = $this->teamsWithHomeVenues($homeVenueIdByTeamId);
        $venues = $this->venuesFor($homeVenueIdByTeamId);
        $rounds = $this->rounds(15, $venues); // Exactly one single cycle (R = N-1 = 15 rounds).
        $config = new GenerationConfig;

        $seed = (new RoundRobinConstructor(new SeededRng(1)))->construct($rounds, $teams, $venues);
        $seedReport = (new ScheduleScorer)->score($seed, $teams, $venues, $config);

        $greedyOnly = (new InitialSolutionBuilder(new SeededRng(1)))->greedyPass($rounds, $teams);
        $greedyReport = (new ScheduleScorer)->score($greedyOnly, $teams, $venues, $config);

        $this->assertTrue($seedReport->hardConstraintsSatisfied);
        $this->assertLessThan($greedyReport->score, $seedReport->score, 'construction should beat a single greedy pass at this scale');

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertLessThanOrEqual($seedReport->score, $result->report->score, 'seed+polish should never be worse than the seed alone');
    }

    private function venuesFor(array $homeVenueIdByTeamId): array
    {
        return array_map(fn (int $venueId) => new VenueInput($venueId, "Venue {$venueId}"), array_values($homeVenueIdByTeamId));
    }

    // --- Strategy dispatch (plan.md "Size-Aware Schedule Generation" §5/§7) ---

    /**
     * Calling generate() without a $strategy argument at all must behave
     * identically to passing GenerationStrategy::SeedAndAnneal explicitly -
     * this is the non-regression requirement backing the optional parameter
     * (every pre-existing test in this file calls the pre-strategy 4-arg
     * signature and continues to pass unmodified, which is the other half
     * of this guarantee).
     */
    public function test_default_strategy_parameter_is_byte_identical_to_explicit_seed_and_anneal()
    {
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(8, $venues);
        $config = new GenerationConfig(timeBudgetMs: 1_000_000_000);

        $implicit = $this->generator(42)->generate($rounds, $teams, $venues, $config);
        $explicit = $this->generator(42)->generate($rounds, $teams, $venues, $config, GenerationStrategy::SeedAndAnneal);

        $this->assertEquals($implicit->candidate, $explicit->candidate);
        $this->assertSame($implicit->attemptsUsed, $explicit->attemptsUsed);
        $this->assertSame('seed_and_anneal', $explicit->report->strategy);
    }

    public function test_seed_only_strategy_returns_the_construction_seed_with_no_annealing()
    {
        // 4 teams / 7 rounds is a genuine multi-cycle regime (R=7 > N-1=3),
        // so SeedAndAnneal on this exact input DOES find annealing work to
        // do (asserted below) - that's what makes "SeedOnly does nothing
        // further" a meaningful claim here rather than a vacuous one.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(7, $venues);
        $config = new GenerationConfig(maxAttempts: 5000, timeBudgetMs: 5000);

        $expectedSeed = (new InitialSolutionBuilder(new SeededRng(1)))->build($rounds, $teams, $venues);

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::SeedOnly);

        $this->assertEquals($expectedSeed, $result->candidate);
        $this->assertSame(0, $result->attemptsUsed, 'no annealing should ever run for SeedOnly');
        $this->assertSame('seed_only', $result->report->strategy);

        $annealed = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::SeedAndAnneal);
        $this->assertGreaterThan(0, $annealed->attemptsUsed, 'sanity check: this input should genuinely give annealing something to do');
    }

    /**
     * Isolates the construction step by using an empty soft-criteria list
     * (hard constraints only), which makes the seed's own score exactly 0.0
     * regardless of strategy - that trips ScheduleGenerator's existing
     * "already perfect, skip the polish phase" short-circuit for BOTH
     * strategies, so what's returned is exactly the raw construction-phase
     * seed with nothing further applied to it, letting a direct equality
     * comparison prove which construction path actually ran.
     */
    public function test_greedy_strategy_skips_round_robin_construction_even_when_eligible()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(7, $venues);
        $config = new GenerationConfig(softCriteria: []);

        $this->assertTrue(
            (new RoundRobinConstructor(new SeededRng(1)))->isEligible($teams, $venues),
            'fixture must be round-robin eligible, otherwise this test cannot distinguish "skipped" from "would have fallen back anyway"'
        );

        $expectedGreedySeed = (new InitialSolutionBuilder(new SeededRng(1)))->greedyPass($rounds, $teams);
        $expectedRoundRobinSeed = (new InitialSolutionBuilder(new SeededRng(1)))->build($rounds, $teams, $venues);
        $this->assertNotEquals($expectedGreedySeed, $expectedRoundRobinSeed, 'sanity check: the two construction paths must actually differ for this input');

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::Greedy);

        $this->assertEquals($expectedGreedySeed, $result->candidate);
        $this->assertSame('greedy', $result->report->strategy);
    }

    public function test_seed_based_strategies_never_carry_a_balanced_opponents_warning()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(10, $venues);
        $config = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);

        foreach ([GenerationStrategy::SeedOnly, GenerationStrategy::SeedAndAnneal] as $strategy) {
            $result = $this->generator(1)->generate($rounds, $teams, $venues, $config, $strategy);

            $this->assertSame([], $result->report->balancedOpponentsViolations, "{$strategy->value} should never carry this warning - the hard constraint is enforced instead");
        }
    }

    /**
     * The greedy path has no whole-schedule visibility, so unlike the
     * seed-based strategies it is not guaranteed to keep every pair's
     * meeting count balanced (plan.md §4) - it runs with the hard
     * constraint OFF instead, and any violation surfaces as a soft
     * review-screen warning (decision 2.6), never a hard failure. Checked
     * across a spread of seeds on a tightly-shaped input (6 teams sharing
     * just 2 venues, mirroring AutomaticScheduleGenerationTest's own
     * real-world fixture) since any single seed's outcome isn't guaranteed.
     */
    public function test_greedy_strategy_can_surface_a_balanced_opponents_warning_while_staying_hard_valid()
    {
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(12, $venues);
        $config = new GenerationConfig(maxAttempts: 50, timeBudgetMs: 200);

        $sawWarning = false;

        for ($seed = 1; $seed <= 25; $seed++) {
            $result = $this->generator($seed)->generate($rounds, $teams, $venues, $config, GenerationStrategy::Greedy);

            $this->assertTrue($result->report->hardConstraintsSatisfied, "seed {$seed} should stay hard-valid even with the balanced-opponents constraint unenforced");
            $this->assertSame('greedy', $result->report->strategy);

            if (! empty($result->report->balancedOpponentsViolations)) {
                $sawWarning = true;
            }
        }

        $this->assertTrue($sawWarning, 'expected at least one of these seeds to surface a balanced-opponents warning on this tightly-shaped input');
    }

    /**
     * plan.md §6/§10 Phase 4b: GenerationStrategy::Exact dispatches straight
     * to ExactSolver rather than the construct-then-anneal pipeline. Uses
     * the flagship 4x6 case (plan.md §1a/§1d/§1f) and cross-checks against
     * calling ExactSolver directly with an identically-seeded Rng, rather
     * than re-deriving the raw [1, 2, 4] counts here (ExactSolverTest
     * already locks those in as equalities) - this test is about the WIRING
     * (does ScheduleGenerator actually reach the solver and carry its
     * result through, not whether the solver itself is correct).
     */
    public function test_exact_strategy_dispatches_to_the_solver_and_reproduces_the_4x6_flagship_optimum()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(6, $venues);
        $config = new GenerationConfig(softCriteria: ['consecutive_venue', 'full_cycle_spacing', 'home_away_break']);

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::Exact);

        $this->assertSame('exact', $result->report->strategy);
        $this->assertTrue($result->report->provenOptimal);

        $expected = (new ExactSolver(new SeededRng(1)))->solve($rounds, $teams, $venues, $config, 10000);

        $this->assertEquals($expected->candidate, $result->candidate);
        $this->assertSame($expected->report->score, $result->report->score);
        $this->assertSame($expected->orderingsExplored, $result->attemptsUsed);
    }

    /**
     * Decision 2.6 (soft failure, never a locked door): an admin can pick
     * Exact for a league whose venue ownership data makes
     * RoundRobinConstructor - and so ExactSolver, which reuses it -
     * ineligible. ExactSolver::solve() THROWS in that situation, so
     * ScheduleGenerator must catch that upstream (via the isEligible()
     * check, backstopped by a try/catch) and degrade to Greedy with a
     * warning rather than ever letting the exception surface.
     */
    public function test_exact_strategy_on_ineligible_input_degrades_to_greedy_with_a_warning_instead_of_throwing()
    {
        // Team 3 has no home venue - the classic ineligible shape.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => null, 4 => 400]);
        $venues = $this->venues(100, 200, 400);
        $rounds = $this->rounds(6, $venues);
        $config = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::Exact);

        $this->assertSame('greedy', $result->report->strategy);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertNull($result->report->provenOptimal, 'Greedy makes no optimality claim, proven or otherwise');
        $this->assertNotNull($result->report->strategyWarning);
        $this->assertStringContainsString('Exact', $result->report->strategyWarning);
        $this->assertStringContainsString('Greedy', $result->report->strategyWarning);
    }

    /**
     * A non-throwing but genuinely infeasible seam for RoundRobinConstructor
     * doesn't exist in practice once isEligible() passes, but this locks in
     * that a requested-but-unavailable Exact never carries a proven-optimal
     * claim regardless of degradation path.
     */
    public function test_exact_strategy_fallback_report_never_claims_proven_optimal()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => null]);
        $venues = $this->venues(100, 200);
        $rounds = $this->rounds(4, $venues);
        $config = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);

        $result = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::Exact);

        $this->assertNotSame('exact', $result->report->strategy);
        $this->assertNull($result->report->provenOptimal);
    }

    /**
     * plan.md §5 Phase 5: the two seam-variant strategies thread their
     * choice all the way from GenerationStrategy through buildSeed() into
     * RoundRobinConstructor::construct()'s $palindromeSeam parameter.
     * Uses an empty soft-criteria list (hard constraints only), which makes
     * the seed's own score exactly 0.0 and trips ScheduleGenerator's
     * "already perfect, skip the polish phase" short-circuit regardless of
     * strategy - so what's returned is exactly the raw construction seed
     * with nothing further applied, letting a direct equality comparison
     * prove which seam mode actually ran.
     */
    public function test_seam_variant_strategies_thread_their_seam_choice_into_construction()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => 400]);
        $venues = $this->venues(100, 200, 300, 400);
        $rounds = $this->rounds(6, $venues);
        $config = new GenerationConfig(softCriteria: []);

        $expectedMirrored = (new RoundRobinConstructor(new SeededRng(1)))->construct($rounds, $teams, $venues, false);
        $expectedPalindrome = (new RoundRobinConstructor(new SeededRng(1)))->construct($rounds, $teams, $venues, true);
        $this->assertNotEquals($expectedMirrored, $expectedPalindrome, 'sanity check: seam choice must actually matter for this fixture');

        $mirroredResult = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::SeedMirroredAndAnneal);
        $this->assertEquals($expectedMirrored, $mirroredResult->candidate);
        $this->assertSame('seed_mirrored_and_anneal', $mirroredResult->report->strategy);

        $palindromeResult = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::SeedPalindromeAndAnneal);
        $this->assertEquals($expectedPalindrome, $palindromeResult->candidate);
        $this->assertSame('seed_palindrome_and_anneal', $palindromeResult->report->strategy);

        // SeedAndAnneal (the plain, pre-Phase-5 case) keeps the mirrored
        // default - this is the non-regression guarantee plan.md's Phase 5
        // section requires.
        $plainResult = $this->generator(1)->generate($rounds, $teams, $venues, $config, GenerationStrategy::SeedAndAnneal);
        $this->assertEquals($expectedMirrored, $plainResult->candidate);
    }
}
