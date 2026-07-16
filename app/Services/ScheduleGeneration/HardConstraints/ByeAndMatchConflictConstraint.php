<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class ByeAndMatchConflictConstraint implements HardConstraint
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
        return 'bye_and_match_conflict';
    }

    public function label(): string
    {
        return 'Team both byed and assigned a match in the same round';
    }

    public function startRound(int $roundIndex): void
    {
        $this->seenThisRound = [];
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $this->seenThisRound[$match->homeTeamId] = true;
        $this->seenThisRound[$match->awayTeamId] = true;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        if (isset($this->seenThisRound[$teamId])) {
            $roundNumber = $roundIndex + 1;
            $this->violations[] = "Round {$roundNumber}: {$this->context->teamLabel($teamId)} was both byed and assigned a match.";
        }
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
