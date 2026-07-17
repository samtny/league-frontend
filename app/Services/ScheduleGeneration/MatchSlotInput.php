<?php

namespace App\Services\ScheduleGeneration;

/**
 * One already-persisted, empty PLMatch row (a "slot") within a Round -
 * created eagerly by Round::createMatches(), one per active venue, before
 * Automatic generation ever runs. The generator only ever fills
 * home_team_id/away_team_id into a slot that already exists; it never
 * creates or deletes match rows.
 */
final class MatchSlotInput
{
    public function __construct(
        public readonly int $matchId,
        public readonly int $venueId,
        public readonly string $venueName,
    ) {
    }
}
