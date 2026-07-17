<?php

namespace App\Services\ScheduleGeneration;

use App\Association;

/**
 * softCriteria is a list of soft-criterion keys, highest priority first -
 * and, critically, ALSO the set of which criteria run at all: a criterion
 * whose key is omitted from this list is never instantiated, never scored,
 * and never contributes a message to the review screen (see
 * SoftCriterionRegistry). tierWeight() converts a key's rank within this
 * list into a dominance ("big-M") weight: each rank's weight is
 * DOMINANCE_BASE times the one below it, so a one-unit improvement in a
 * higher-ranked criterion always outweighs the maximum possible sum of
 * every lower-ranked criterion combined, while the whole thing still
 * collapses into one smooth scalar objective (required for simulated
 * annealing's probabilistic accept/reject, which can't compare on a strict
 * per-tier basis). Each SoftCriterion normalizes its own raw penalty to a
 * small, roughly scale-invariant range before this weight is applied, so
 * the same priority ordering behaves consistently whether the league has 4
 * teams or 16.
 */
final class GenerationConfig
{
    public const DOMINANCE_BASE = 100;

    public const DEFAULT_SOFT_CRITERIA = [
        'equal_matches_played',
        'home_away_balance',
        'home_venue_balance',
        'repeat_opponent_consecutive_rounds',
        'opponent_recency',
        'home_away_break',
        'consecutive_venue',
    ];

    /**
     * Calibrated so a 16-team / 16-active-venue / 10-round schedule
     * (SlotCount::total() = 16 venues x 10 rounds = 160 - team count itself
     * doesn't affect slot count) lands its epoch-scaled attempts budget
     * (searchEpochs x SlotCount::total()) exactly on the default
     * max_attempts ceiling (100000, see config/schedule_generation.php):
     * 625 x 160 = 100000. Smaller schedules scale down proportionally from
     * there. LARGER league sizes are explicitly OUT OF SCOPE for this
     * calibration for now - they simply get capped at max_attempts instead
     * of receiving additional headroom, same as before this was calibrated.
     * If that ever needs to change, revisit both this constant and
     * max_attempts together, not in isolation.
     */
    public const DEFAULT_SEARCH_EPOCHS = 625;

    /**
     * maxAttempts/timeBudgetMs bound simulated-annealing iterations (each a
     * single cheap local move + rescore) rather than full-schedule
     * randomized restarts, so the default is set much higher than the old
     * restart-loop default. This is the TOTAL budget for the whole
     * EpsilonConstraintOptimizer search, not one pass: it splits both
     * across the count($softCriteria) sequential passes (one per tier),
     * giving the highest-priority not-yet-fixed tier the largest share each
     * time - see EpsilonConstraintOptimizer for the exact split.
     *
     * maxAttempts specifically is a defensive CEILING, not the primary
     * driver of how much searching happens: EpsilonConstraintOptimizer
     * actually targets searchEpochs full sweeps of the schedule's own
     * neighborhood (SlotCount::total($rounds) - roughly rounds x venues,
     * see DEFAULT_SEARCH_EPOCHS's docblock for the exact calibration
     * point), so the real attempts budget scales with problem size instead
     * of being a flat number regardless of whether the league has 4 teams
     * or 40 - UP TO that calibration point (a 16-team/16-venue/10-round
     * schedule); larger leagues are out of scope for now and just get
     * capped at maxAttempts like everything above the calibration point,
     * rather than scaling further. maxAttempts also binds whenever a caller
     * deliberately passes a small value to force a tight ceiling (as
     * several tests do). timeBudgetMs deliberately does NOT scale the same
     * way - it stays a flat wall-clock ceiling, since generation runs
     * synchronously inside an HTTP request and a bigger league shouldn't
     * mean a slower page load.
     *
     * @param string[] $softCriteria soft-criterion keys, highest priority first - a key's ABSENCE disables that
     *   criterion entirely, it is not merely deprioritized. May be a proper subset of the known keys, or even an
     *   empty array (hard constraints only, no soft scoring at all).
     * @param string[] $excludedFromObjective soft-criterion keys to zero out of tierWeight() entirely - used by
     *   EpsilonConstraintOptimizer to remove already-fixed tiers from a pass's objective without disturbing the
     *   relative dominance exponents of the remaining tiers (which stay derived from the full $softCriteria order)
     */
    public function __construct(
        public readonly int $maxAttempts = 5000,
        public readonly int $timeBudgetMs = 2000,
        public readonly int $searchEpochs = self::DEFAULT_SEARCH_EPOCHS,
        public readonly array $softCriteria = self::DEFAULT_SOFT_CRITERIA,
        public readonly array $excludedFromObjective = [],
    ) {
    }

    public function tierWeight(string $key): float
    {
        if (in_array($key, $this->excludedFromObjective, true)) {
            return 0.0;
        }

        $rank = array_search($key, $this->softCriteria, true);

        if ($rank === false) {
            // Dead in normal flow: ScheduleScorer only ever calls weight()
            // on a criterion it built from $config->softCriteria itself, so
            // its key is always found above. Kept as a safety net for a
            // hand-built SoftCriterion used outside ScheduleScorer::score().
            return 1.0;
        }

        return (float) (self::DOMINANCE_BASE ** (count($this->softCriteria) - 1 - $rank));
    }

    public static function fromConfig(): self
    {
        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 5000),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 2000),
            searchEpochs: (int) config('schedule_generation.search_epochs', self::DEFAULT_SEARCH_EPOCHS),
            softCriteria: self::sanitizeSoftCriteria(config('schedule_generation.soft_criteria', self::DEFAULT_SOFT_CRITERIA)),
        );
    }

    /**
     * Per-league override: an Association may choose which criteria run,
     * and in what order, via schedule_generation_settings->soft_criteria.
     * Falls back to the system default whenever that's unset or malformed,
     * rather than letting a bad value silently break tierWeight() for a key
     * that never resolves to a rank.
     */
    public static function forAssociation(Association $association): self
    {
        $settings = $association->schedule_generation_settings ?? [];

        // The system default itself comes from the config file (falling
        // back to the hardcoded constant only if that file's own value is
        // somehow malformed) - an association's override, if any, is then
        // validated against THAT, not the raw constant, so editing
        // config('schedule_generation.soft_criteria') actually changes what
        // every association without its own override uses.
        $systemDefault = self::sanitizeSoftCriteria(
            config('schedule_generation.soft_criteria', self::DEFAULT_SOFT_CRITERIA),
            self::DEFAULT_SOFT_CRITERIA,
        );

        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 5000),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 2000),
            searchEpochs: (int) config('schedule_generation.search_epochs', self::DEFAULT_SEARCH_EPOCHS),
            softCriteria: self::sanitizeSoftCriteria($settings['soft_criteria'] ?? null, $systemDefault),
        );
    }

    /**
     * Accepts any duplicate-free subset of the known soft-criterion keys,
     * in the given order - including an explicit empty array, which means
     * "no soft criteria, hard constraints only" rather than "malformed."
     * Falls back to $fallback whenever the value isn't an array, or
     * contains an unknown key, or contains a duplicate.
     *
     * @param mixed $softCriteria
     * @param string[] $fallback
     * @return string[]
     */
    private static function sanitizeSoftCriteria($softCriteria, array $fallback = self::DEFAULT_SOFT_CRITERIA): array
    {
        if (! is_array($softCriteria)) {
            return $fallback;
        }

        $values = array_values($softCriteria);

        if (count($values) !== count(array_unique($values))) {
            return $fallback;
        }

        if (array_diff($values, self::DEFAULT_SOFT_CRITERIA) !== []) {
            return $fallback;
        }

        return $values;
    }
}
