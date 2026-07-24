<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\ExactSolver;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\RoundRobinConstructor;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

/**
 * Locks in the measured-optimal values from plan.md ("Size-Aware Schedule
 * Generation..." section 1d/1f) as EQUALITIES, not bounds - those numbers
 * came from an independent prototype (and, at 4x6, a full brute force of
 * every one of the 5,760 valid schedules), so if ExactSolver ever disagrees
 * with one of them the solver is wrong, not the fixture.
 */
class ExactSolverTest extends TestCase
{
    /**
     * Every team owns a distinct venue - the case the plan.md table was
     * measured against, and the only shape where "proven optimum" is a
     * meaningful, unambiguous claim (a shared venue pair introduces extra
     * hard-constraint bookkeeping that isn't what this table is testing).
     *
     * @return array{0: TeamInput[], 1: VenueInput[]}
     */
    private function teamsWithDistinctVenues(int $n): array
    {
        $teams = [];
        $venues = [];

        for ($id = 1; $id <= $n; $id++) {
            $venues[] = new VenueInput($id, "Venue {$id}");
            $teams[] = new TeamInput($id, "Team {$id}", $id);
        }

        return [$teams, $venues];
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

    /**
     * Priority order the plan.md table was measured under:
     * consecutive_venue > full_cycle_spacing > home_away_break, each its
     * own tier (no ties) so the dominance weighting between them is
     * unambiguous.
     */
    private function priorityConfig(): GenerationConfig
    {
        return new GenerationConfig(softCriteria: ['consecutive_venue', 'full_cycle_spacing', 'home_away_break']);
    }

    private function solver(int $seed): ExactSolver
    {
        return new ExactSolver(new SeededRng($seed));
    }

    /**
     * Raw same-venue-repeat count: for every (team, round) where the team
     * played and its venue matches the venue it played at in its own
     * immediately preceding PLAYED round (a bye in between resets this,
     * mirroring ConsecutiveVenueCriterion's own bye handling) - counted
     * directly from the candidate, independent of any scoring weight or
     * the repeat-offender surcharge ConsecutiveVenueCriterion's rawPenalty()
     * adds on top of this.
     */
    private function countSameVenueRepeats(ScheduleCandidate $candidate): int
    {
        $lastVenueByTeam = [];
        $count = 0;

        foreach ($candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
                    if (($lastVenueByTeam[$teamId] ?? null) === $match->venueId) {
                        $count++;
                    }
                }
            }

            foreach ($round->matches as $match) {
                $lastVenueByTeam[$match->homeTeamId] = $match->venueId;
                $lastVenueByTeam[$match->awayTeamId] = $match->venueId;
            }

            foreach ($round->byeTeamIds as $teamId) {
                unset($lastVenueByTeam[$teamId]);
            }
        }

        return $count;
    }

    /**
     * Raw home/away-role-repeat ("break") count: for every (team, round)
     * where the team played the same role (home or away) as it did in its
     * own immediately preceding PLAYED round - again a bye resets this,
     * and this is the plain per-occurrence count before
     * HomeAwayBreakCriterion's 3x severe-streak multiplier.
     */
    private function countHomeAwayBreaks(ScheduleCandidate $candidate): int
    {
        $lastRoleByTeam = [];
        $count = 0;

        foreach ($candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                if (($lastRoleByTeam[$match->homeTeamId] ?? null) === true) {
                    $count++;
                }

                if (($lastRoleByTeam[$match->awayTeamId] ?? null) === false) {
                    $count++;
                }
            }

            foreach ($round->matches as $match) {
                $lastRoleByTeam[$match->homeTeamId] = true;
                $lastRoleByTeam[$match->awayTeamId] = false;
            }

            foreach ($round->byeTeamIds as $teamId) {
                unset($lastRoleByTeam[$teamId]);
            }
        }

        return $count;
    }

    /**
     * Raw full-cycle-spacing shortfall: sum over every rematch of
     * max(0, (activeTeamCount - 1) - gapInRounds), matching
     * FullCycleSpacingCriterion's own shortfall definition exactly (gap is
     * purely round-index arithmetic - a bye in between does not reset it,
     * since FullCycleSpacingCriterion never resets on bye either).
     */
    private function countFullCycleSpacingShortfall(ScheduleCandidate $candidate, int $teamCount): int
    {
        $lastMeetingRound = [];
        $shortfall = 0;
        $fullCycleGap = $teamCount - 1;

        foreach ($candidate->rounds as $roundIndex => $round) {
            foreach ($round->matches as $match) {
                $key = $match->homeTeamId < $match->awayTeamId
                    ? "{$match->homeTeamId}-{$match->awayTeamId}"
                    : "{$match->awayTeamId}-{$match->homeTeamId}";

                if (isset($lastMeetingRound[$key])) {
                    $gap = $roundIndex - $lastMeetingRound[$key];
                    $shortfall += max(0, $fullCycleGap - $gap);
                }

                $lastMeetingRound[$key] = $roundIndex;
            }
        }

        return $shortfall;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $expected  [venue repeats, home/away breaks, spacing shortfall]
     */
    private function assertProvenOptimum(int $teams, int $roundCount, array $expected, int $timeBudgetMs = 10000): void
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues($teams);
        $rounds = $this->rounds($roundCount, $activeVenues);

        $result = $this->solver(1)->solve($rounds, $activeTeams, $activeVenues, $this->priorityConfig(), $timeBudgetMs);

        $this->assertTrue(
            $result->provenOptimal,
            "expected the {$teams}x{$roundCount} search to complete within the {$timeBudgetMs}ms budget"
        );

        $actual = [
            $this->countSameVenueRepeats($result->candidate),
            $this->countHomeAwayBreaks($result->candidate),
            $this->countFullCycleSpacingShortfall($result->candidate, $teams),
        ];

        $this->assertSame($expected, $actual, "proven optimum mismatch for {$teams}x{$roundCount}");
    }

    public function test_4x6_reproduces_the_flagship_proven_optimum()
    {
        // The flagship case from plan.md 1a/1e: full brute force of all
        // 5,760 valid 4x6 schedules independently confirmed [1, 2, 4] is
        // the best achievable once consecutive_venue outranks
        // full_cycle_spacing - a 3-week same-venue run is unavoidable if
        // full_cycle_spacing is ranked first instead (plan.md 1a).
        $this->assertProvenOptimum(4, 6, [1, 2, 4]);
    }

    public function test_4x10()
    {
        $this->assertProvenOptimum(4, 10, [1, 2, 12]);
    }

    public function test_5x6()
    {
        $this->assertProvenOptimum(5, 6, [0, 0, 0]);
    }

    public function test_5x10()
    {
        $this->assertProvenOptimum(5, 10, [0, 0, 0]);
    }

    public function test_6x6()
    {
        $this->assertProvenOptimum(6, 6, [2, 4, 0]);
    }

    public function test_6x8()
    {
        $this->assertProvenOptimum(6, 8, [2, 4, 18]);
    }

    public function test_6x10()
    {
        // The largest case in plan.md's own measurement (22,680 orderings,
        // 8.2s in a PHP prototype) - given a generous budget rather than
        // the 10s default so timing variance on CI hardware doesn't turn
        // this into a flaky "best found, not proven" result.
        $this->assertProvenOptimum(6, 10, [2, 4, 36], timeBudgetMs: 30000);
    }

    public function test_7x6()
    {
        $this->assertProvenOptimum(7, 6, [0, 0, 0]);
    }

    /**
     * Cross-check against the orderings counts plan.md 1f measured
     * directly (30 / 1,680 / 120 / 22,680 / 120 / 1,260) - if the
     * enumeration rule (which cycle rounds get the "extra" use when
     * rounds isn't a multiple of the cycle length, and fixing round 0 to
     * cycle round 0) ever drifts from what was actually measured, this
     * fails even if the final optimum happens to still match.
     */
    public function test_orderings_explored_matches_the_measured_counts()
    {
        $cases = [
            [4, 6, 30],
            [4, 10, 1680],
            [5, 6, 120],
            [6, 6, 120],
            [6, 8, 1260],
            [7, 6, 120],
        ];

        foreach ($cases as [$teams, $roundCount, $expectedOrderings]) {
            [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues($teams);
            $rounds = $this->rounds($roundCount, $activeVenues);

            $result = $this->solver(1)->solve($rounds, $activeTeams, $activeVenues, $this->priorityConfig(), 10000);

            $this->assertTrue($result->provenOptimal, "{$teams}x{$roundCount} should complete within budget");
            $this->assertSame(
                $expectedOrderings,
                $result->orderingsExplored,
                "{$teams}x{$roundCount} should explore exactly the orderings count measured in plan.md 1f"
            );
        }
    }

    public function test_result_is_hard_valid_per_the_real_schedule_scorer()
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues(6);
        $rounds = $this->rounds(8, $activeVenues);

        $result = $this->solver(3)->solve($rounds, $activeTeams, $activeVenues, $this->priorityConfig());

        $this->assertTrue($result->report->hardConstraintsSatisfied);
        $this->assertSame([], $result->report->hardViolations);

        // Re-score independently as a sanity check that the report shipped
        // with the result actually reflects the candidate it's paired with.
        $independentReport = (new ScheduleScorer)->score($result->candidate, $activeTeams, $activeVenues, $this->priorityConfig());
        $this->assertSame($independentReport->score, $result->report->score);
    }

    public function test_every_match_is_played_at_the_home_teams_own_venue()
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues(6);
        $rounds = $this->rounds(8, $activeVenues);

        $result = $this->solver(4)->solve($rounds, $activeTeams, $activeVenues, $this->priorityConfig());

        $homeVenueIdByTeam = [];
        foreach ($activeTeams as $team) {
            $homeVenueIdByTeam[$team->id] = $team->homeVenueId;
        }

        foreach ($result->candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $this->assertSame($homeVenueIdByTeam[$match->homeTeamId], $match->venueId);
            }
        }
    }

    public function test_team_to_slot_assignment_varies_across_seeds()
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues(5);
        $rounds = $this->rounds(6, $activeVenues);

        $roundZeroHomeTeamsBySeed = [];

        foreach ([1, 2, 3, 4, 5] as $seed) {
            $result = $this->solver($seed)->solve($rounds, $activeTeams, $activeVenues, $this->priorityConfig());
            $roundZeroHomeTeamsBySeed[$seed] = array_map(
                fn ($match) => $match->homeTeamId,
                $result->candidate->rounds[0]->matches,
            );
        }

        $this->assertGreaterThan(
            1,
            count(array_unique(array_map(fn ($teams) => implode(',', $teams), $roundZeroHomeTeamsBySeed))),
            'team-to-slot assignment should vary across seeds (the fairness mechanism the plan requires)'
        );
    }

    /**
     * The critical safety guarantee: an artificially tiny budget (0ms)
     * must make the deadline check trip before a single ordering is even
     * evaluated, so the result can only ever be the seed
     * RoundRobinConstructor itself would have produced - never worse,
     * because nothing but the seed was ever considered.
     */
    public function test_a_tiny_budget_never_returns_worse_than_the_seed()
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues(6);
        $rounds = $this->rounds(10, $activeVenues);
        $config = $this->priorityConfig();

        $result = $this->solver(42)->solve($rounds, $activeTeams, $activeVenues, $config, timeBudgetMs: 0);

        $this->assertFalse($result->provenOptimal);
        $this->assertSame(0, $result->orderingsExplored);

        // Same seed value fed independently to a bare RoundRobinConstructor
        // must reproduce byte-identical output, since ExactSolver::solve()
        // consumes the injected Rng in exactly the same way (isEligible()
        // draws nothing, construct() is the very first draw) before any
        // search state is touched.
        $expectedSeed = (new RoundRobinConstructor(new SeededRng(42)))->construct($rounds, $activeTeams, $activeVenues);
        $expectedReport = (new ScheduleScorer)->score($expectedSeed, $activeTeams, $activeVenues, $config);

        $this->assertEquals($expectedSeed, $result->candidate);
        $this->assertSame($expectedReport->score, $result->report->score);
    }

    /**
     * Same guarantee, but with a budget just barely large enough to
     * evaluate a handful of orderings rather than zero - still must never
     * beat what's structurally impossible to beat within that few
     * orderings, but more importantly must never regress below the seed
     * even mid-search.
     */
    public function test_a_small_budget_that_times_out_mid_search_is_never_worse_than_the_seed()
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues(6);
        $rounds = $this->rounds(10, $activeVenues);
        $config = $this->priorityConfig();

        $expectedSeed = (new RoundRobinConstructor(new SeededRng(42)))->construct($rounds, $activeTeams, $activeVenues);
        $expectedSeedReport = (new ScheduleScorer)->score($expectedSeed, $activeTeams, $activeVenues, $config);

        $result = $this->solver(42)->solve($rounds, $activeTeams, $activeVenues, $config, timeBudgetMs: 5);

        $this->assertFalse($result->provenOptimal);
        $this->assertLessThanOrEqual($expectedSeedReport->score, $result->report->score);
    }

    public function test_solve_throws_for_ineligible_input()
    {
        // A team with no home venue makes RoundRobinConstructor::isEligible()
        // false - ExactSolver has nothing to seed the incumbent with and no
        // venue to build a cycle against, so it must refuse rather than
        // silently degrade (mirrors RoundRobinConstructor::construct()'s own
        // precondition, just enforced with an exception since ExactSolver
        // has no "null means ineligible" return convention to fall back on).
        $teams = [
            new TeamInput(1, 'Team 1', 100),
            new TeamInput(2, 'Team 2', 200),
            new TeamInput(3, 'Team 3', null),
        ];
        $venues = [new VenueInput(100, 'Venue 100'), new VenueInput(200, 'Venue 200')];

        $this->expectException(\RuntimeException::class);

        $this->solver(1)->solve($this->rounds(4, $venues), $teams, $venues, $this->priorityConfig());
    }

    public function test_empty_rounds_returns_an_empty_proven_optimal_candidate()
    {
        [$activeTeams, $activeVenues] = $this->teamsWithDistinctVenues(4);

        $result = $this->solver(1)->solve([], $activeTeams, $activeVenues, $this->priorityConfig());

        $this->assertSame([], $result->candidate->rounds);
        $this->assertTrue($result->provenOptimal);
        $this->assertSame(0, $result->orderingsExplored);
    }
}
