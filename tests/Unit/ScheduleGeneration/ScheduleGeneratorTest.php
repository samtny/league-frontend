<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
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
     * @param array<int, int|null> $homeVenueIdByTeamId
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
     * @param VenueInput[] $venues
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
}
