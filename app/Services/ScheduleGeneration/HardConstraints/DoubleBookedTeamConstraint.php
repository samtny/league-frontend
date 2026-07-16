<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class DoubleBookedTeamConstraint implements HardConstraint
{
    /** @var array<int, bool> */
    private array $seenThisRound = [];

    /** @var string[] */
    private array $violations = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'double_booked_team';
    }

    public function label(): string
    {
        return 'Team assigned to more than one match in a round';
    }

    public function startRound(int $roundIndex): void
    {
        $this->seenThisRound = [];
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;

        foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
            if (isset($this->seenThisRound[$teamId])) {
                $this->violations[] = "Round {$roundNumber}: {$this->context->teamLabel($teamId)} was assigned to more than one match.";
            }

            $this->seenThisRound[$teamId] = true;
        }
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
