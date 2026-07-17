<?php

namespace App\Services\ScheduleGeneration;

interface Rng
{
    public function nextInt(int $minInclusive, int $maxInclusive): int;

    /**
     * Uniform float in [0, 1) - used for simulated annealing's Metropolis
     * accept/reject probability.
     */
    public function nextFloat(): float;

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    public function shuffle(array $items): array;
}
