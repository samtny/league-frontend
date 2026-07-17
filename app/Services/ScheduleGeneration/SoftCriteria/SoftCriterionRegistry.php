<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\ScoringContext;

/**
 * The single source of truth for "soft-criterion key -> how to build it."
 * ScheduleScorer uses build() to construct only the criteria a
 * GenerationConfig actually enables (GenerationConfig::$softCriteria) - an
 * omitted key is never instantiated, so it never runs at all.
 */
final class SoftCriterionRegistry
{
    private const FACTORIES = [
        'home_cycle_spacing' => HomeCycleSpacingCriterion::class,
        'away_cycle_spacing' => AwayCycleSpacingCriterion::class,
        'equal_matches_played' => EqualMatchesPlayedCriterion::class,
        'home_away_balance' => HomeAwayBalanceCriterion::class,
        'home_venue_balance' => HomeVenueBalanceCriterion::class,
        'repeat_opponent_consecutive_rounds' => RepeatOpponentConsecutiveRoundsCriterion::class,
        'full_cycle_spacing' => FullCycleSpacingCriterion::class,
        'rematch_home_away_reversal' => RematchHomeAwayReversalCriterion::class,
        'home_away_break' => HomeAwayBreakCriterion::class,
        'consecutive_venue' => ConsecutiveVenueCriterion::class,
    ];

    /**
     * @param string[] $keys
     * @return SoftCriterion[]
     */
    public static function build(array $keys, ScoringContext $context): array
    {
        $criteria = [];

        foreach ($keys as $key) {
            if (isset(self::FACTORIES[$key])) {
                $class = self::FACTORIES[$key];
                $criteria[] = new $class($context);
            }
        }

        return $criteria;
    }

    /**
     * @return string[]
     */
    public static function knownKeys(): array
    {
        return array_keys(self::FACTORIES);
    }
}
