<?php

namespace App\Services\ScheduleGeneration;

/**
 * One already-persisted Round, plus its already-persisted match slots. The
 * caller decides which Rounds are eligible for assignment (today: every
 * Round on the schedule; later, once a Round "active"/"type" flag exists,
 * a filtered subset) - the generator has no opinion on why a Round is or
 * isn't in this list.
 */
final class RoundInput
{
    /** @param MatchSlotInput[] $slots */
    public function __construct(
        public readonly int $roundId,
        public readonly \DateTimeImmutable $date,
        public readonly array $slots,
    ) {
    }
}
