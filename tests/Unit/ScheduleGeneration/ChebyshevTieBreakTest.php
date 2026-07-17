<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\ChebyshevTieBreak;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\InitialSolutionBuilder;
use App\Services\ScheduleGeneration\MatchSlotInput;
use App\Services\ScheduleGeneration\RoundInput;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class ChebyshevTieBreakTest extends TestCase
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

    public function test_optimize_never_produces_a_hard_invalid_candidate_and_scores_every_tied_member()
    {
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(8, $venues);
        $config = new GenerationConfig(maxAttempts: 300, timeBudgetMs: 800);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(5)))->greedyPass($rounds, $teams);

        $outcome = (new ChebyshevTieBreak(new SeededRng(5), $scorer))->optimize(
            ['home_cycle_spacing', 'away_cycle_spacing'], $seed, $rounds, $teams, $venues, $config, [],
        );

        $this->assertTrue($outcome['report']->hardConstraintsSatisfied);
        $this->assertNotNull($outcome['report']->criterion('home_cycle_spacing'));
        $this->assertNotNull($outcome['report']->criterion('away_cycle_spacing'));
    }

    public function test_the_worse_off_members_raw_penalty_never_regresses_from_the_seed()
    {
        // Chebyshev/minimax's whole point: protect the WORST case, not each
        // member individually - see ChebyshevTieBreak's own docblock. The
        // worse-off (by raw penalty) of the two tied members at the seed
        // should never end up worse in the final result.
        $teams = $this->teams(1, 2, 3, 4, 5, 6);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(8, $venues);
        $config = new GenerationConfig(maxAttempts: 300, timeBudgetMs: 800);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(6)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);

        $outcome = (new ChebyshevTieBreak(new SeededRng(6), $scorer))->optimize(
            ['home_cycle_spacing', 'away_cycle_spacing'], $seed, $rounds, $teams, $venues, $config, [],
        );

        $seedWorst = max(
            $seedReport->criterion('home_cycle_spacing')['raw'],
            $seedReport->criterion('away_cycle_spacing')['raw'],
        );
        $finalWorst = max(
            $outcome['report']->criterion('home_cycle_spacing')['raw'],
            $outcome['report']->criterion('away_cycle_spacing')['raw'],
        );

        $this->assertLessThanOrEqual($seedWorst + 1e-6, $finalWorst);
    }

    public function test_fixed_tier_thresholds_are_still_respected_during_the_joint_pass()
    {
        // A tier fixed by an EARLIER (higher-priority) pass must still gate
        // every candidate the joint pass considers, exactly like a
        // singleton-tier SimulatedAnnealingOptimizer pass - confirmed by
        // passing an impossible-to-satisfy threshold and checking the
        // result never violates it.
        $teams = $this->teams(1, 2, 3, 4);
        $venues = $this->venues(10, 20);
        $rounds = $this->rounds(6, $venues);
        $config = new GenerationConfig(maxAttempts: 200, timeBudgetMs: 500);
        $scorer = new ScheduleScorer;

        $seed = (new InitialSolutionBuilder(new SeededRng(7)))->greedyPass($rounds, $teams);
        $seedReport = $scorer->score($seed, $teams, $venues, $config);
        $earlierTierRaw = $seedReport->criterion('equal_matches_played')['raw'];

        $outcome = (new ChebyshevTieBreak(new SeededRng(7), $scorer))->optimize(
            ['home_cycle_spacing', 'away_cycle_spacing'],
            $seed,
            $rounds,
            $teams,
            $venues,
            $config,
            ['equal_matches_played' => $earlierTierRaw],
        );

        $this->assertLessThanOrEqual(
            $earlierTierRaw + 1e-9,
            $outcome['report']->criterion('equal_matches_played')['raw'],
        );
    }
}
