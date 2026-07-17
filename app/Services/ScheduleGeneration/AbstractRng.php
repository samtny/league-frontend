<?php

namespace App\Services\ScheduleGeneration;

abstract class AbstractRng implements Rng
{
    public function nextFloat(): float
    {
        return $this->nextInt(0, 1_000_000_000) / 1_000_000_000;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    public function shuffle(array $items): array
    {
        $items = array_values($items);

        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->nextInt(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }
}
