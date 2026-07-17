<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;

/**
 * A weighted penalty contributing to a candidate schedule's score.
 * ScheduleScorer replays a candidate round by round, feeding every match and
 * bye to each registered criterion, then calls finalize() once all rounds
 * have been observed (for criteria whose penalty depends on final totals
 * rather than a per-match delta) before reading penalty()/messages().
 */
interface SoftCriterion
{
    public function key(): string;

    public function label(): string;

    public function observeMatch(int $roundIndex, MatchCandidate $match): void;

    public function observeBye(int $roundIndex, int $teamId): void;

    /**
     * Convert accumulated per-observation state into final aggregates.
     * No-op for criteria that are already fully incremental.
     */
    public function finalize(): void;

    /**
     * weight(config) * a normalized, roughly scale-invariant measure of how
     * badly this criterion is violated (e.g. raw occurrence count divided by
     * team/match/round count as appropriate) - so the same priority ordering
     * behaves consistently whether the league has 4 teams or 16.
     */
    public function penalty(GenerationConfig $config): float;

    /**
     * This criterion's dominance-scaled priority weight (see
     * GenerationConfig::tierWeight()) - independent of how many instances of
     * the violation actually occurred.
     */
    public function weight(GenerationConfig $config): float;

    /**
     * The intrinsic, config-independent normalized penalty - the same value
     * penalty() multiplies by weight(). Exists on its own so it can be
     * compared across GenerationConfigs whose weight() for this criterion
     * differs (e.g. epsilon-constraint search, where a criterion's weight
     * changes pass to pass but its raw penalty doesn't).
     */
    public function rawPenalty(): float;

    /**
     * "1 raw unit" on this criterion's own normalized scale - e.g. what one
     * additional occurrence of the underlying violation would cost
     * rawPenalty() before any priority weighting. Used as the epsilon-
     * constraint tolerance: a candidate may worsen this criterion's raw
     * penalty by up to one unit relative to the best achieved so far.
     */
    public function epsilonUnit(): float;

    /**
     * @return string[]
     */
    public function messages(): array;
}
