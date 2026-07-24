<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationReport;
use App\Services\ScheduleGeneration\GenerationStrategy;
use Tests\TestCase;

/**
 * plan.md "Size-Aware Schedule Generation" §6/§10 Phase 4b: $provenOptimal
 * (ExactSolverResult::$provenOptimal, carried through to the review screen)
 * and $strategyWarning (ScheduleGenerator::degradeExactToGreedy()'s
 * soft-failure note) both need to survive the session round trip
 * (toArray()/fromArray()) the same way $strategy and
 * $balancedOpponentsViolations already do, since GenerationReport crosses a
 * session boundary between generateMatchesStore() and
 * generateMatchesReview() - see ScheduleController.
 */
class GenerationReportTest extends TestCase
{
    private function baseReport(): GenerationReport
    {
        return new GenerationReport(
            hardConstraintsSatisfied: true,
            hardViolations: [],
            softViolationsByCriterion: ['consecutive_venue' => ['Team 1 played at home 3 rounds running']],
            score: 12.5,
            degenerate: false,
        );
    }

    public function test_proven_optimal_true_survives_the_array_round_trip()
    {
        $report = $this->baseReport()->withStrategyMetadata(GenerationStrategy::Exact, [], provenOptimal: true);

        $restored = GenerationReport::fromArray($report->toArray());

        $this->assertTrue($restored->provenOptimal);
        $this->assertSame('exact', $restored->strategy);
    }

    public function test_proven_optimal_false_survives_the_array_round_trip()
    {
        $report = $this->baseReport()->withStrategyMetadata(GenerationStrategy::Exact, [], provenOptimal: false);

        $restored = GenerationReport::fromArray($report->toArray());

        $this->assertFalse($restored->provenOptimal);
    }

    /**
     * Every strategy OTHER than Exact makes no optimality claim at all -
     * null, not false, since false would falsely imply the strategy tried
     * and failed to prove optimality.
     */
    public function test_proven_optimal_defaults_to_null_and_survives_the_round_trip()
    {
        $report = $this->baseReport()->withStrategyMetadata(GenerationStrategy::SeedOnly);

        $this->assertNull($report->provenOptimal);

        $restored = GenerationReport::fromArray($report->toArray());

        $this->assertNull($restored->provenOptimal);
    }

    public function test_strategy_warning_survives_the_array_round_trip()
    {
        $report = $this->baseReport()
            ->withStrategyMetadata(GenerationStrategy::Greedy)
            ->withStrategyWarning('Exact was selected, but this league is not eligible. Generated with Greedy instead.');

        $restored = GenerationReport::fromArray($report->toArray());

        $this->assertSame(
            'Exact was selected, but this league is not eligible. Generated with Greedy instead.',
            $restored->strategyWarning,
        );
        // withStrategyWarning() must not disturb any other field it copies.
        $this->assertSame('greedy', $restored->strategy);
        $this->assertSame($report->score, $restored->score);
    }

    public function test_strategy_warning_defaults_to_null_and_survives_the_round_trip()
    {
        $report = $this->baseReport()->withStrategyMetadata(GenerationStrategy::SeedAndAnneal);

        $this->assertNull($report->strategyWarning);

        $restored = GenerationReport::fromArray($report->toArray());

        $this->assertNull($restored->strategyWarning);
    }

    /**
     * fromArray() must tolerate session data written before Phase 4b added
     * these two keys (an in-flight generation started before a deploy,
     * e.g.) rather than erroring on a missing array key.
     */
    public function test_from_array_tolerates_missing_phase_4b_keys()
    {
        $data = $this->baseReport()->withStrategyMetadata(GenerationStrategy::SeedOnly)->toArray();
        unset($data['proven_optimal'], $data['strategy_warning']);

        $restored = GenerationReport::fromArray($data);

        $this->assertNull($restored->provenOptimal);
        $this->assertNull($restored->strategyWarning);
    }

    public function test_with_strategy_metadata_preserves_an_existing_strategy_warning_by_default()
    {
        $report = $this->baseReport()
            ->withStrategyMetadata(GenerationStrategy::Greedy)
            ->withStrategyWarning('some warning');

        // Re-stamping strategy metadata (as ScheduleGenerator does once,
        // not twice, in practice) should not silently drop a warning that
        // was already attached.
        $restamped = $report->withStrategyMetadata(GenerationStrategy::Greedy, ['a violation']);

        $this->assertSame('some warning', $restamped->strategyWarning);
        $this->assertSame(['a violation'], $restamped->balancedOpponentsViolations);
    }
}
