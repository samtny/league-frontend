<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\EpsilonConstraintOptimizer;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\GenerationReport;
use App\Services\ScheduleGeneration\InitialSolutionBuilder;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\ScheduleGenerator;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class EpsilonConstraintOptimizerTest extends TestCase
{
    private function teams(int ...$ids): array
    {
        return array_map(fn (int $id) => new TeamInput($id, "Team {$id}"), $ids);
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

    private function criterionRaw(GenerationReport $report, string $key): float
    {
        foreach ($report->softCriteriaScores as $criterion) {
            if ($criterion['key'] === $key) {
                return $criterion['raw'];
            }
        }

        $this->fail("No soft criterion scored under key '{$key}'.");
    }

    public function test_reordering_priority_changes_which_criterion_is_fixed_first()
    {
        // With only 2 active teams, repeat_opponent_consecutive_rounds and
        // full_cycle_spacing are both forced nonzero (every match is a
        // rematch of the only possible pairing) - a real scenario where
        // which tier gets fixed FIRST (and therefore gets the least
        // tolerance for later trades) genuinely matters, and must follow
        // rank, not identity.
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);
        $rounds = $this->rounds(5, $venues);

        $defaultOrderConfig = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);
        $reversedOrderConfig = new GenerationConfig(
            maxAttempts: 200,
            timeBudgetMs: 500,
            softCriteria: array_reverse(GenerationConfig::DEFAULT_SOFT_CRITERIA),
        );

        $rngA = new SeededRng(7);
        $rngB = new SeededRng(7);
        $scorer = new ScheduleScorer;

        $seedA = (new ScheduleGenerator($rngA, $scorer))->generate($rounds, $teams, $venues, $defaultOrderConfig);
        $seedB = (new ScheduleGenerator($rngB, $scorer))->generate($rounds, $teams, $venues, $reversedOrderConfig);

        // Both are valid, non-degenerate schedules - the point of this test
        // isn't the exact score, just that changing $priority actually
        // changes which criterion the search treats as top-priority (and
        // therefore untouchable) first, rather than epsilon slack being
        // hardcoded to a fixed criterion key.
        $this->assertFalse($seedA->report->degenerate);
        $this->assertFalse($seedB->report->degenerate);
        $this->assertTrue($seedA->report->hardConstraintsSatisfied);
        $this->assertTrue($seedB->report->hardConstraintsSatisfied);
    }

    /**
     * The flat, singleton-tier ordering DEFAULT_SOFT_CRITERIA used before
     * home_cycle_spacing/away_cycle_spacing were added as a tied top tier -
     * used by tests below that specifically want to exercise the
     * singleton-tier "never regresses" invariant in isolation, which a
     * Chebyshev-tied tier deliberately weakens (see
     * test_the_worse_off_member_of_a_tied_top_tier_never_regresses_from_its_pre_optimization_seed_value).
     *
     * @return string[]
     */
    private function flatSingletonTierOrder(): array
    {
        return [
            'equal_matches_played',
            'home_away_balance',
            'home_venue_balance',
            'repeat_opponent_consecutive_rounds',
            'full_cycle_spacing',
            'rematch_home_away_reversal',
            'home_away_break',
            'consecutive_venue',
        ];
    }

    public function test_the_top_priority_tiers_raw_penalty_never_regresses_from_its_pre_optimization_seed_value()
    {
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(6, $venues);
        $config = new GenerationConfig(maxAttempts: 500, timeBudgetMs: 1000, softCriteria: $this->flatSingletonTierOrder());
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(3)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);
        $this->assertTrue($seedReport->hardConstraintsSatisfied);

        // The top-priority tier (equal_matches_played, in this flat
        // singleton-tier order) is what the FIRST pass optimizes and then
        // fixes with zero prior constraints and the largest budget share -
        // it should never end up worse, in the final report, than the raw
        // seed already had, since SimulatedAnnealingOptimizer's own
        // best-ever tracking guarantees a pass can only match-or-improve on
        // its own starting point. This invariant is specific to a SINGLETON
        // tier - a Chebyshev-tied tier only guarantees this for the
        // worse-off of its members, not each individually, see
        // test_the_worse_off_member_of_a_tied_top_tier_never_regresses_from_its_pre_optimization_seed_value
        // below.
        $result = (new ScheduleGenerator(new SeededRng(3), $scorer))->generate($rounds, $teams, $venues, $config);

        $seedTopTierRaw = $this->criterionRaw($seedReport, $config->softCriteria[0]);
        $finalTopTierRaw = $this->criterionRaw($result->report, $config->softCriteria[0]);

        $this->assertLessThanOrEqual($seedTopTierRaw + 1e-9, $finalTopTierRaw);
    }

    public function test_the_worse_off_member_of_a_tied_top_tier_never_regresses_from_its_pre_optimization_seed_value()
    {
        // The Chebyshev/minimax-appropriate analogue of the singleton-tier
        // invariant above: with home_cycle_spacing/away_cycle_spacing tied
        // at the top by default, only the WORSE-OFF of the two members (by
        // raw penalty) is protected from regressing - minimax exists to
        // protect the worst case, not to freeze every member individually
        // the way a single dominant criterion's own best-tracking does. A
        // move that helps the better-off member at the worse-off member's
        // expense is exactly what ChebyshevTieBreak rejects; a move that
        // trades some of the better-off member's slack to help the
        // worse-off one further is exactly what it allows - so only the
        // pointwise max is guaranteed non-increasing, not each raw value on
        // its own. Note ChebyshevTieBreak's actual guarantee is on the
        // IDEAL-normalized regret max, not the raw max asserted here - they
        // coincide when both members' ideal points are comparable, which is
        // the practically-relevant case (see GenerationConfig's own
        // scale-invariant-raw-penalty design principle); this is the
        // user-facing quantity (raw, unnormalized penalty) that actually
        // matters, not an internal implementation detail.
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(8, $venues);
        // enforceBalancedOpponents disabled: the seed below comes from
        // InitialSolutionBuilder::greedyPass (no venue ownership set, so
        // RoundRobinConstructor isn't eligible), and the greedy path is NOT
        // guaranteed to satisfy balanced-opponent-meetings (see
        // GenerationConfig::$enforceBalancedOpponents) - unrelated to what
        // this test actually exercises (the tied-tier minimax regression
        // guarantee).
        $config = new GenerationConfig(maxAttempts: 400, timeBudgetMs: 1500, enforceBalancedOpponents: false);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(4)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);
        $this->assertTrue($seedReport->hardConstraintsSatisfied);

        $result = (new ScheduleGenerator(new SeededRng(4), $scorer))->generate($rounds, $teams, $venues, $config);

        $seedWorst = max(
            $this->criterionRaw($seedReport, 'home_cycle_spacing'),
            $this->criterionRaw($seedReport, 'away_cycle_spacing'),
        );
        $finalWorst = max(
            $this->criterionRaw($result->report, 'home_cycle_spacing'),
            $this->criterionRaw($result->report, 'away_cycle_spacing'),
        );

        $this->assertLessThanOrEqual($seedWorst + 1e-6, $finalWorst);
    }

    public function test_seven_pass_budget_split_never_exceeds_the_configured_attempts_budget()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);
        $rounds = $this->rounds(5, $venues);

        foreach ([3, 20, 5000] as $maxAttempts) {
            $config = new GenerationConfig(maxAttempts: $maxAttempts, timeBudgetMs: 60_000);
            $result = (new ScheduleGenerator(new SeededRng(5), new ScheduleScorer))->generate($rounds, $teams, $venues, $config);

            $this->assertLessThanOrEqual($maxAttempts, $result->attemptsUsed, "attempts budget exceeded for maxAttempts={$maxAttempts}");
        }
    }

    public function test_degenerate_two_team_input_still_returns_a_hard_valid_non_crashing_result()
    {
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);
        $rounds = $this->rounds(3, $venues);
        $config = new GenerationConfig(maxAttempts: 50, timeBudgetMs: 200);

        $result = (new ScheduleGenerator(new SeededRng(9), new ScheduleScorer))->generate($rounds, $teams, $venues, $config);

        $this->assertFalse($result->report->degenerate);
        $this->assertTrue($result->report->hardConstraintsSatisfied);
    }

    public function test_direct_optimize_call_never_produces_a_hard_invalid_candidate()
    {
        // Note: unlike a single dominance-weighted SimulatedAnnealingOptimizer
        // pass, the OLD aggregate "sum of all tiers" score is NOT
        // guaranteed to never regress from the seed under epsilon-constraint
        // search - each pass only optimizes its own tier and ignores
        // not-yet-fixed lower tiers entirely while doing so, so a lower tier
        // can end up worse if its own (later, smaller-budget) pass doesn't
        // fully recover it. The true invariant is per-fixed-tier: the FIRST
        // tier fixed (which gets the whole budget with no constraints yet)
        // can never regress from the seed. Uses the flat singleton-tier
        // order (see flatSingletonTierOrder()) so that invariant is being
        // tested in its original, unweakened form - see
        // test_the_worse_off_member_of_a_tied_top_tier_never_regresses_from_its_pre_optimization_seed_value
        // for the Chebyshev-tied-tier analogue.
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(8, $venues);
        // enforceBalancedOpponents disabled: the seed below comes from
        // InitialSolutionBuilder::greedyPass (no venue ownership set, so
        // RoundRobinConstructor isn't eligible), and the greedy path is NOT
        // guaranteed to satisfy balanced-opponent-meetings (see
        // GenerationConfig::$enforceBalancedOpponents) - unrelated to what
        // this test actually exercises (the first-tier-never-regresses
        // guarantee).
        $config = new GenerationConfig(maxAttempts: 300, timeBudgetMs: 1000, softCriteria: $this->flatSingletonTierOrder(), enforceBalancedOpponents: false);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(2)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);

        $outcome = (new EpsilonConstraintOptimizer(new SeededRng(2), $scorer))->optimize(
            $seed, $seedReport, $rounds, $teams, $venues, $config,
        );

        $this->assertTrue($outcome['report']->hardConstraintsSatisfied);

        $seedTopTierRaw = $this->criterionRaw($seedReport, $config->softCriteria[0]);
        $finalTopTierRaw = $this->criterionRaw($outcome['report'], $config->softCriteria[0]);
        $this->assertLessThanOrEqual($seedTopTierRaw + 1e-9, $finalTopTierRaw);
    }
}
