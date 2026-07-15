<?php

namespace App\Services\ScheduleGeneration;

/**
 * Deterministic RNG for tests: identical seed + inputs always produce
 * identical output, so generator behavior can be asserted exactly rather
 * than just "looks plausible."
 */
final class SeededRng extends AbstractRng
{
    public function __construct(int $seed)
    {
        mt_srand($seed);
    }

    public function nextInt(int $minInclusive, int $maxInclusive): int
    {
        return mt_rand($minInclusive, $maxInclusive);
    }
}
