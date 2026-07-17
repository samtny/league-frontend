<?php

namespace App\Services\ScheduleGeneration;

/**
 * One already-persisted Round, plus its already-persisted match slots. The
 * caller decides which Rounds are eligible for assignment - e.g.
 * ScheduleController::generateAutomaticCandidate() excludes any Round
 * flagged off_week or playoffs_week - the generator has no opinion on why
 * a Round is or isn't in this list.
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
