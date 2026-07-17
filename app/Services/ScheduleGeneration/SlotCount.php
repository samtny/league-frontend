<?php

namespace App\Services\ScheduleGeneration;

/**
 * Shared by SimulatedAnnealingOptimizer (cooling-schedule epoch size) and
 * EpsilonConstraintOptimizer (scaling the total search budget to problem
 * size), so "how big is this schedule's neighborhood" is defined in exactly
 * one place.
 */
final class SlotCount
{
    /**
     * @param RoundInput[] $rounds
     */
    public static function total(array $rounds): int
    {
        $count = 0;

        foreach ($rounds as $round) {
            $count += count($round->slots);
        }

        return $count;
    }
}
