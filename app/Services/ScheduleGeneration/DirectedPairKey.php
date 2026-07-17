<?php

namespace App\Services\ScheduleGeneration;

/**
 * Unlike PairKey (symmetric - "these two teams," order doesn't matter),
 * HomeCycleSpacingCriterion/AwayCycleSpacingCriterion track a specific
 * team's OWN role-scoped history against a specific opponent, where
 * direction matters: "team A hosting team B" and "team B hosting team A"
 * are different, independently-tracked events.
 */
final class DirectedPairKey
{
    public static function for(int $actorTeamId, int $opponentTeamId): string
    {
        return "{$actorTeamId}->{$opponentTeamId}";
    }
}
