<?php

namespace App\Services\ScheduleGeneration;

interface Rng
{
    public function nextInt(int $minInclusive, int $maxInclusive): int;

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    public function shuffle(array $items): array;
}
