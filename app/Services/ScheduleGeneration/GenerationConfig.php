<?php

namespace App\Services\ScheduleGeneration;

use App\Association;

/**
 * Priority is expressed as a ranked list of the 7 soft-criteria keys
 * (highest priority first), not raw numeric weights - a league is meant to
 * reorder criteria, not tune magic numbers. tierWeight() converts a rank
 * into a dominance ("big-M") weight: each rank's weight is DOMINANCE_BASE
 * times the one below it, so a one-unit improvement in a higher-ranked
 * criterion always outweighs the maximum possible sum of every lower-ranked
 * criterion combined, while the whole thing still collapses into one smooth
 * scalar objective (required for simulated annealing's probabilistic
 * accept/reject, which can't compare on a strict per-tier basis). Each
 * SoftCriterion normalizes its own raw penalty to a small, roughly
 * scale-invariant range before this weight is applied, so the same priority
 * ordering behaves consistently whether the league has 4 teams or 16.
 */
final class GenerationConfig
{
    public const DOMINANCE_BASE = 100;

    public const DEFAULT_PRIORITY = [
        'equal_matches_played',
        'home_away_balance',
        'home_venue_balance',
        'repeat_opponent_consecutive_rounds',
        'opponent_recency',
        'home_away_break',
        'consecutive_venue',
    ];

    /**
     * maxAttempts now bounds simulated-annealing iterations (each a single
     * cheap local move + rescore) rather than full-schedule randomized
     * restarts, so the default is set much higher than the old restart-loop
     * default.
     *
     * @param string[] $priority soft-criterion keys, highest priority first
     */
    public function __construct(
        public readonly int $maxAttempts = 5000,
        public readonly int $timeBudgetMs = 2000,
        public readonly array $priority = self::DEFAULT_PRIORITY,
    ) {
    }

    public function tierWeight(string $key): float
    {
        $rank = array_search($key, $this->priority, true);

        if ($rank === false) {
            return 1.0;
        }

        return (float) (self::DOMINANCE_BASE ** (count($this->priority) - 1 - $rank));
    }

    public static function fromConfig(): self
    {
        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 5000),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 2000),
            priority: self::sanitizePriority(config('schedule_generation.default_priority', self::DEFAULT_PRIORITY)),
        );
    }

    /**
     * Per-league override: an Association may rank the 7 criteria
     * differently via schedule_generation_settings->priority. Falls back to
     * the system default whenever that's unset or malformed, rather than
     * letting a bad value silently break tierWeight() for a key that never
     * resolves to a rank.
     */
    public static function forAssociation(Association $association): self
    {
        $settings = $association->schedule_generation_settings ?? [];

        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 5000),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 2000),
            priority: self::sanitizePriority($settings['priority'] ?? null),
        );
    }

    /**
     * @param mixed $priority
     * @return string[]
     */
    private static function sanitizePriority($priority): array
    {
        if (! is_array($priority)) {
            return self::DEFAULT_PRIORITY;
        }

        $expected = self::DEFAULT_PRIORITY;
        sort($expected);
        $actual = array_values($priority);
        sort($actual);

        return $expected === $actual ? array_values($priority) : self::DEFAULT_PRIORITY;
    }
}
