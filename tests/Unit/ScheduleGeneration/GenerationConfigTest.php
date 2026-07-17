<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Association;
use App\Services\ScheduleGeneration\EpsilonConstraintOptimizer;
use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\RoundCandidate;
use App\Services\ScheduleGeneration\ScheduleCandidate;
use App\Services\ScheduleGeneration\ScheduleScorer;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use Tests\TestCase;

class GenerationConfigTest extends TestCase
{
    private function association(?array $settings): Association
    {
        return new Association(['schedule_generation_settings' => $settings]);
    }

    public function test_a_subset_of_known_keys_is_accepted_in_the_given_order()
    {
        $config = GenerationConfig::forAssociation($this->association([
            'soft_criteria' => ['home_venue_balance', 'home_away_balance'],
        ]));

        $this->assertSame(['home_venue_balance', 'home_away_balance'], $config->softCriteria);
    }

    public function test_an_explicit_empty_array_is_accepted_as_hard_constraints_only()
    {
        $config = GenerationConfig::forAssociation($this->association(['soft_criteria' => []]));

        $this->assertSame([], $config->softCriteria);
    }

    public function test_an_unknown_key_falls_back_to_the_default()
    {
        $config = GenerationConfig::forAssociation($this->association([
            'soft_criteria' => ['home_away_balance', 'not_a_real_key'],
        ]));

        $this->assertSame(config('schedule_generation.soft_criteria'), $config->softCriteria);
    }

    public function test_a_duplicate_key_falls_back_to_the_default()
    {
        $config = GenerationConfig::forAssociation($this->association([
            'soft_criteria' => ['home_away_balance', 'home_away_balance'],
        ]));

        $this->assertSame(config('schedule_generation.soft_criteria'), $config->softCriteria);
    }

    public function test_a_non_array_value_falls_back_to_the_default()
    {
        $config = GenerationConfig::forAssociation($this->association(['soft_criteria' => 'home_away_balance']));

        $this->assertSame(config('schedule_generation.soft_criteria'), $config->softCriteria);
    }

    public function test_missing_or_null_settings_fall_back_to_the_default()
    {
        $this->assertSame(config('schedule_generation.soft_criteria'), GenerationConfig::forAssociation($this->association(null))->softCriteria);
        $this->assertSame(config('schedule_generation.soft_criteria'), GenerationConfig::forAssociation($this->association([]))->softCriteria);
    }

    public function test_epsilon_constraint_optimizer_does_no_search_work_when_soft_criteria_is_empty()
    {
        $teams = [new TeamInput(1, 'Team 1'), new TeamInput(2, 'Team 2')];
        $venues = [new VenueInput(10, 'Venue 10')];
        $seed = new ScheduleCandidate([
            new RoundCandidate(new \DateTimeImmutable('2026-07-06'), [new MatchCandidate(10, 'Venue 10', 1, 2)], []),
        ]);

        $config = new GenerationConfig(softCriteria: []);
        $scorer = new ScheduleScorer;
        $seedReport = $scorer->score($seed, $teams, $venues, $config);

        $outcome = (new EpsilonConstraintOptimizer(new SeededRng(1), $scorer))->optimize(
            $seed, $seedReport, [], $teams, $venues, $config,
        );

        $this->assertSame(0, $outcome['iterations']);
        $this->assertSame($seed, $outcome['candidate']);
        $this->assertSame(0.0, $outcome['report']->score);
    }
}
