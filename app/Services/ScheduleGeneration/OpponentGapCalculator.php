<?php

namespace App\Services\ScheduleGeneration;

/**
 * Shared by RoundBuilder (construction-time opponent tie-break) and the
 * scorer's opponent-recency criterion, so "rounds since these two teams last
 * met" is defined in exactly one place.
 */
final class OpponentGapCalculator
{
    public static function sinceLastMeeting(int $roundIndex, ?int $lastMeetingRoundIndex): ?int
    {
        return $lastMeetingRoundIndex === null ? null : $roundIndex - $lastMeetingRoundIndex;
    }
}
