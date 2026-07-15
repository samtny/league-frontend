<?php

namespace App\Services\ScheduleGeneration;

/**
 * Production RNG backed by the CSPRNG. Bound to Rng::class in
 * AppServiceProvider for real requests.
 */
final class MtRng extends AbstractRng
{
    public function nextInt(int $minInclusive, int $maxInclusive): int
    {
        return random_int($minInclusive, $maxInclusive);
    }
}
