<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

/**
 * Shared bookkeeping for criteria whose violations are tied to a specific
 * round: records which team(s) were flagged in which round (deduplicated),
 * for roundViolations() to expose to the review screen's per-team indicator.
 */
trait RecordsRoundViolations
{
    /** @var array<int, array<int, true>> round index => set of flagged team IDs */
    private array $roundViolationTeams = [];

    private function flagRoundViolation(int $roundIndex, int ...$teamIds): void
    {
        foreach ($teamIds as $teamId) {
            $this->roundViolationTeams[$roundIndex][$teamId] = true;
        }
    }

    /**
     * @return array<int, int[]> round index => distinct team IDs flagged in that round
     */
    public function roundViolations(): array
    {
        return array_map('array_keys', $this->roundViolationTeams);
    }
}
