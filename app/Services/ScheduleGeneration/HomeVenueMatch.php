<?php

namespace App\Services\ScheduleGeneration;

/**
 * Shared by RoundBuilder (construction-time host selection) and the scorer's
 * away-team/home-venue-balance rules, so "is this team at their own home
 * venue" is defined in exactly one place.
 */
final class HomeVenueMatch
{
    public static function isOwnVenue(?int $homeVenueId, ?int $venueId): bool
    {
        return $homeVenueId !== null && $homeVenueId === $venueId;
    }
}
