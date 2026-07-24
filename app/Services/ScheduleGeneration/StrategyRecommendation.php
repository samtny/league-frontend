<?php

namespace App\Services\ScheduleGeneration;

/**
 * StrategyRecommender's output: a default GenerationStrategy plus a
 * human-readable reason shown to the admin on the select screen. This is
 * always a DEFAULT, never a restriction (plan.md decision 2.7) - every
 * GenerationStrategy case stays selectable regardless of what is
 * recommended here.
 */
final class StrategyRecommendation
{
    public function __construct(
        public readonly GenerationStrategy $strategy,
        public readonly string $reason,
    ) {}
}
