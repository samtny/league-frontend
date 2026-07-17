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
     * @return string[]
     */
    public function messages(): array;
}
