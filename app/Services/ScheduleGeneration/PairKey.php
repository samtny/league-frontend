<?php

namespace App\Services\ScheduleGeneration;

/**
 * A team pairing's identity doesn't depend on who was home vs. away, so
 * "last met" bookkeeping needs an order-independent key.
 */
final class PairKey
{
    public static function for(int $teamAId, int $teamBId): string
    {
        return $teamAId < $teamBId ? "{$teamAId}-{$teamBId}" : "{$teamBId}-{$teamAId}";
    }
}
