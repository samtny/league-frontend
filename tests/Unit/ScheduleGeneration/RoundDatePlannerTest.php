<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\RoundDatePlanner;
use Tests\TestCase;

class RoundDatePlannerTest extends TestCase
{
    public function test_july_2026_has_four_mondays()
    {
        $dates = (new RoundDatePlanner)->datesFor('2026-07-01', '2026-07-31', 'mon');

        $this->assertSame(['2026-07-06', '2026-07-13', '2026-07-20', '2026-07-27'], array_map(
            fn (\DateTimeImmutable $d) => $d->format('Y-m-d'),
            $dates,
        ));
    }

    public function test_weekday_matching_is_case_insensitive()
    {
        $dates = (new RoundDatePlanner)->datesFor('2026-07-01', '2026-07-07', 'MON');

        $this->assertCount(1, $dates);
        $this->assertSame('2026-07-06', $dates[0]->format('Y-m-d'));
    }

    public function test_start_and_end_date_are_both_inclusive()
    {
        $dates = (new RoundDatePlanner)->datesFor('2026-07-06', '2026-07-06', 'mon');

        $this->assertCount(1, $dates);
    }

    public function test_no_matching_weekday_returns_empty()
    {
        $dates = (new RoundDatePlanner)->datesFor('2026-07-01', '2026-07-01', 'mon');

        $this->assertSame([], $dates);
    }
}
