<?php

namespace App\Services\ScheduleGeneration;

final class GenerationConfig
{
    public function __construct(
        public readonly int $maxAttempts = 500,
        public readonly int $timeBudgetMs = 1500,
        public readonly float $weightVenue = 5.0,
        public readonly float $weightEquality = 8.0,
        public readonly float $weightRepeat = 3.0,
        public readonly float $weightHomeAway = 2.0,
        public readonly float $weightHomeVenueBalance = 6.0,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 500),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 1500),
            weightVenue: (float) config('schedule_generation.weights.venue', 5.0),
            weightEquality: (float) config('schedule_generation.weights.equality', 8.0),
            weightRepeat: (float) config('schedule_generation.weights.repeat', 3.0),
            weightHomeAway: (float) config('schedule_generation.weights.home_away', 2.0),
            weightHomeVenueBalance: (float) config('schedule_generation.weights.home_venue_balance', 6.0),
        );
    }
}
