<?php

namespace App\Services\ScheduleGeneration;

final class GenerationConfig
{
    public function __construct(
        public readonly int $maxAttempts = 500,
        public readonly int $timeBudgetMs = 1500,
        public readonly float $weightVenue = 1.0,
        public readonly float $weightEquality = 1.0,
        public readonly float $weightRepeat = 1.0,
        public readonly float $weightHomeAway = 1.0,
        public readonly float $weightHomeVenueBalance = 1.0,
        public readonly float $weightHomeAwayBreak = 1.0,
        public readonly float $weightConsecutiveOpponentRepeat = 1.0,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 500),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 1500),
            weightVenue: (float) config('schedule_generation.weights.venue', 1.0),
            weightEquality: (float) config('schedule_generation.weights.equality', 1.0),
            weightRepeat: (float) config('schedule_generation.weights.repeat', 1.0),
            weightHomeAway: (float) config('schedule_generation.weights.home_away', 1.0),
            weightHomeVenueBalance: (float) config('schedule_generation.weights.home_venue_balance', 1.0),
            weightHomeAwayBreak: (float) config('schedule_generation.weights.home_away_break', 1.0),
            weightConsecutiveOpponentRepeat: (float) config('schedule_generation.weights.consecutive_opponent_repeat', 1.0),
        );
    }
}
