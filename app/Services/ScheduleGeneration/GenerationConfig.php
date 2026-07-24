<?php

namespace App\Services\ScheduleGeneration;

use App\Association;
use App\Services\ScheduleGeneration\SoftCriteria\SoftCriterionRegistry;

/**
 * softCriteria is a list of tiers, highest priority first - and, critically,
 * ALSO the set of which criteria run at all: a criterion whose key doesn't
 * appear anywhere in this list is never instantiated, never scored, and
 * never contributes a message to the review screen (see
 * SoftCriterionRegistry). Each tier element is EITHER a bare string (a
 * single criterion holding that rank alone, the common case) OR an array of
 * 2+ strings (a "tie-group" - co-equal criteria sharing that rank). See
 * tiers() for the normalized array<int, string[]> view every other consumer
 * uses.
 *
 * tierWeight() converts a key's TIER INDEX into a dominance ("big-M")
 * weight: each rank's weight is DOMINANCE_BASE times the one below it, so a
 * one-unit improvement in a higher-ranked tier always outweighs the maximum
 * possible sum of every lower-ranked tier combined, while the whole thing
 * still collapses into one smooth scalar objective (required for simulated
 * annealing's probabilistic accept/reject, which can't compare on a strict
 * per-tier basis) - every member of the SAME tier gets the identical
 * weight, by construction. Each SoftCriterion normalizes its own raw
 * penalty to a small, roughly scale-invariant range before this weight is
 * applied, so the same priority ordering behaves consistently whether the
 * league has 4 teams or 16.
 *
 * A tie-group's members are NOT combined via this additive weighted sum
 * among themselves, though - EpsilonConstraintOptimizer resolves them
 * jointly via ChebyshevTieBreak (minimax on normalized regret from each
 * member's own best-achievable value) instead, precisely so neither member
 * can be freely traded away for the other at an arbitrary ratio the way a
 * plain sum would allow. See ChebyshevTieBreak's own docblock for why
 * Chebyshev/minimax was chosen and what alternative (goal programming) is
 * worth exploring later.
 */
final class GenerationConfig
{
    public const DOMINANCE_BASE = 100;

    public const DEFAULT_SOFT_CRITERIA = [
        ['home_cycle_spacing', 'away_cycle_spacing'],
        'equal_matches_played',
        'home_away_balance',
        'home_venue_balance',
        'repeat_opponent_consecutive_rounds',
        'full_cycle_spacing',
        'rematch_home_away_reversal',
        'home_away_break',
        'balanced_opponents',
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
     * @param  array<int, string|string[]>  $softCriteria  tiers, highest priority first - a bare string is a
     *                                                     singleton tier, an array of 2+ strings is a co-equal tie-group (see class docblock). A key's ABSENCE
     *                                                     anywhere in this structure disables that criterion entirely, it is not merely deprioritized. May cover a
     *                                                     proper subset of the known keys, or even an empty array (hard constraints only, no soft scoring at all).
     * @param  string[]  $excludedFromObjective  soft-criterion keys to zero out of tierWeight() entirely - used by
     *                                           EpsilonConstraintOptimizer to remove already-fixed tiers from a pass's objective without disturbing the
     *                                           relative dominance exponents of the remaining tiers (which stay derived from the full $softCriteria order)
     * @param  bool  $enforceBalancedOpponents  whether ScheduleScorer registers BalancedOpponentMeetingsConstraint (every
     *                                          unordered pair of active teams must meet between floor(M/P) and ceil(M/P) times, M = total matches, P = pairs)
     *                                          as a HARD constraint. Defaults true because RoundRobinConstructor's seed-based strategies satisfy this by
     *                                          construction and the exact solver enforces it structurally too - but RoundBuilder's greedy fallback path
     *                                          almost certainly does NOT satisfy it, and SimulatedAnnealingOptimizer's opponentRecombine/roundRebuild moves
     *                                          can break it even starting from a valid seed. Registering the constraint unconditionally would turn schedules
     *                                          the greedy path currently produces successfully into hard-invalid degenerate results with no better path
     *                                          available, so the greedy strategy runs with this OFF and surfaces a warning instead (soft failure, not a
     *                                          silent regression). See plan.md ("Size-Aware Schedule Generation") §4.
     * @param  int  $exactSolverTimeBudgetMs  wall-clock budget ExactSolver::solve() gets for GenerationStrategy::Exact
     *                                        (plan.md §6/§10 Phase 4b) - deliberately its OWN config key rather than reusing $timeBudgetMs, since the
     *                                        exact solver is a fundamentally different, much more expensive search than EpsilonConstraintOptimizer's
     *                                        annealing passes (which $timeBudgetMs bounds) and product explicitly signed off on a much larger default
     *                                        (10 seconds vs. 2) for it alone - see plan.md decision 2.4 and §11's "10 seconds inside a synchronous HTTP
     *                                        request" risk note. ScheduleGenerator reads this rather than relying on solve()'s own default argument, so
     *                                        it stays admin/environment configurable the same way every other budget here is.
     */
    public function __construct(
        public readonly int $maxAttempts = 5000,
        public readonly int $timeBudgetMs = 2000,
        public readonly int $searchEpochs = self::DEFAULT_SEARCH_EPOCHS,
        public readonly array $softCriteria = self::DEFAULT_SOFT_CRITERIA,
        public readonly array $excludedFromObjective = [],
        public readonly bool $enforceBalancedOpponents = true,
        public readonly int $exactSolverTimeBudgetMs = 10000,
    ) {}

    /**
     * Normalizes $softCriteria so every tier is a string[] of one or more
     * co-equal keys, even a plain-string singleton tier (the common case) -
     * the uniform view every other consumer (tierWeight(), flatSoftCriteria(),
     * EpsilonConstraintOptimizer) actually works with.
     *
     * @return array<int, string[]>
     */
    public function tiers(): array
    {
        return array_map(fn ($tier) => is_array($tier) ? $tier : [$tier], $this->softCriteria);
    }

    /**
     * Every enabled key across every tier, flattened in tier order
     * (tie-group members keep their given relative order) - what
     * ScheduleScorer actually needs to know which SoftCriterion instances
     * to build.
     *
     * @return string[]
     */
    public function flatSoftCriteria(): array
    {
        $tiers = $this->tiers();

        return $tiers === [] ? [] : array_merge(...$tiers);
    }

    public function tierWeight(string $key): float
    {
        if (in_array($key, $this->excludedFromObjective, true)) {
            return 0.0;
        }

        $tiers = $this->tiers();

        foreach ($tiers as $rank => $tierKeys) {
            if (in_array($key, $tierKeys, true)) {
                return (float) (self::DOMINANCE_BASE ** (count($tiers) - 1 - $rank));
            }
        }

        // Dead in normal flow: ScheduleScorer only ever calls weight() on a
        // criterion it built from $config->flatSoftCriteria() itself, so its
        // key is always found above. Kept as a safety net for a hand-built
        // SoftCriterion used outside ScheduleScorer::score().
        return 1.0;
    }

    public static function fromConfig(): self
    {
        return new self(
            maxAttempts: (int) config('schedule_generation.max_attempts', 5000),
            timeBudgetMs: (int) config('schedule_generation.time_budget_ms', 2000),
            searchEpochs: (int) config('schedule_generation.search_epochs', self::DEFAULT_SEARCH_EPOCHS),
            softCriteria: self::sanitizeSoftCriteria(config('schedule_generation.soft_criteria', self::DEFAULT_SOFT_CRITERIA)),
            enforceBalancedOpponents: (bool) config('schedule_generation.enforce_balanced_opponents', true),
            exactSolverTimeBudgetMs: (int) config('schedule_generation.exact_solver_time_budget_ms', 10000),
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
            enforceBalancedOpponents: (bool) config('schedule_generation.enforce_balanced_opponents', true),
            exactSolverTimeBudgetMs: (int) config('schedule_generation.exact_solver_time_budget_ms', 10000),
        );
    }

    /**
     * Accepts any duplicate-free set of the known soft-criterion keys, in
     * the given tier order - a tier element may be a bare string (singleton)
     * or a non-empty array of 2+ unique strings (a tie-group) - including an
     * explicit empty array, which means "no soft criteria, hard constraints
     * only" rather than "malformed." Falls back to $fallback whenever the
     * value isn't an array; a tier element is neither a string nor a
     * non-empty array of strings; the FLATTENED set of every key (across
     * every tier, including inside tie-groups) contains an unknown key; or
     * that flattened set contains a duplicate (whether within one tie-group
     * or across two different tiers).
     *
     * @param  mixed  $softCriteria
     * @param  array<int, string|string[]>  $fallback
     * @return array<int, string|string[]>
     */
    private static function sanitizeSoftCriteria($softCriteria, array $fallback = self::DEFAULT_SOFT_CRITERIA): array
    {
        if (! is_array($softCriteria)) {
            return $fallback;
        }

        $tiers = array_values($softCriteria);
        $flatKeys = [];

        foreach ($tiers as $tier) {
            if (is_string($tier)) {
                $flatKeys[] = $tier;

                continue;
            }

            if (! is_array($tier) || $tier === []) {
                return $fallback;
            }

            foreach ($tier as $memberKey) {
                if (! is_string($memberKey)) {
                    return $fallback;
                }

                $flatKeys[] = $memberKey;
            }
        }

        if (count($flatKeys) !== count(array_unique($flatKeys))) {
            return $fallback;
        }

        if (array_diff($flatKeys, SoftCriterionRegistry::knownKeys()) !== []) {
            return $fallback;
        }

        return $tiers;
    }
}
