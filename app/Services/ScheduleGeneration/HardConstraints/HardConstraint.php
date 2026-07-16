<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;

/**
 * A boolean gate on a candidate schedule: any violation invalidates it,
 * independent of score. ScheduleScorer replays a candidate round by round,
 * feeding every match and bye to each registered constraint.
 */
interface HardConstraint
{
    public function key(): string;

    public function label(): string;

    /**
     * Reset any round-scoped state (e.g. "seen this round") before this
     * round's matches/byes are observed. No-op for constraints that only
     * need cross-round state.
     */
    public function startRound(int $roundIndex): void;

    public function observeMatch(int $roundIndex, MatchCandidate $match): void;

    public function observeBye(int $roundIndex, int $teamId): void;

    /**
     * @return string[]
     */
    public function violations(): array;
}
