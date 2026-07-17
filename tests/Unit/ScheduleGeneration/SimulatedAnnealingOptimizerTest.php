<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\GenerationReport;
use App\Services\ScheduleGeneration\InitialSolutionBuilder;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\SimulatedAnnealingOptimizer;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class SimulatedAnnealingOptimizerTest extends TestCase
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

    public function test_a_custom_tie_break_objective_overrides_the_default_score_based_best_tracking()
    {
        // With only 2 teams they're forced to face each other every round,
        // guaranteeing real, nonzero soft-criteria pressure (repeat
        // opponents, home/away breaks, etc.) for the search to have
        // something to actually improve on under the DEFAULT ->score
        // objective.
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);
        $rounds = $this->rounds(5, $venues);
        $config = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(1)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);
        $this->assertTrue($seedReport->hardConstraintsSatisfied);
        $this->assertGreaterThan(0.0, $seedReport->score);

        // A constant objective makes EVERY candidate look identical to the
        // search, no matter how ->score itself moves - so best-tracking
        // (which must read the override, not ->score, to behave this way)
        // can never find anything that looks like an improvement over the
        // seed, and the seed is returned untouched.
        $constantObjective = fn (GenerationReport $report): float => 1.0;

        $outcome = (new SimulatedAnnealingOptimizer(new SeededRng(1), $scorer))->optimize(
            $seed, $seedReport, $rounds, $teams, $venues, $config, microtime(true), [], $constantObjective,
        );

        $this->assertSame($seed, $outcome['candidate']);
        $this->assertSame($seedReport, $outcome['report']);
    }

    public function test_omitting_the_tie_break_objective_falls_back_to_plain_score()
    {
        // Same setup as above, but with NO override - the default
        // ->score-driven search should behave exactly as it always has,
        // finding something at least as good as (score <=) the seed.
        $teams = $this->teams(1, 2);
        $venues = $this->venues(10);
        $rounds = $this->rounds(5, $venues);
        $config = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(1)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);
        $this->assertTrue($seedReport->hardConstraintsSatisfied);

        $outcome = (new SimulatedAnnealingOptimizer(new SeededRng(1), $scorer))->optimize(
            $seed, $seedReport, $rounds, $teams, $venues, $config, microtime(true),
        );

        $this->assertTrue($outcome['report']->hardConstraintsSatisfied);
        $this->assertLessThanOrEqual($seedReport->score, $outcome['report']->score);
    }
}
