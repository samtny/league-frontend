<?php

namespace App\Services\ScheduleGeneration;

/**
 * Shared by manual and automatic generation: steps every calendar day from
 * start to end date (inclusive) and keeps the ones matching the schedule's
 * weekday.
 */
final class RoundDatePlanner
{
    /**
     * @return \DateTimeImmutable[]
     */
    public function datesFor(string $startDate, string $endDate, string $weekday): array
    {
        $weekday = strtolower($weekday);
        $end = new \DateTimeImmutable($endDate);

        $dates = [];

        for ($date = new \DateTimeImmutable($startDate); $date <= $end; $date = $date->add(new \DateInterval('P1D'))) {
            if (strtolower($date->format('D')) === $weekday) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
