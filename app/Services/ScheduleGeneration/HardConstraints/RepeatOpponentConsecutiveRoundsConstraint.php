<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class RepeatOpponentConsecutiveRoundsConstraint implements HardConstraint
{
    /** @var array<int, int|null> team id => opponent in the immediately preceding round */
    private array $lastOpponentByTeam = [];

    /** @var string[] */
    private array $violations = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'repeat_opponent_consecutive_rounds';
    }

    public function label(): string
    {
        return 'Same opponent in consecutive rounds';
    }

    public function startRound(int $roundIndex): void
    {
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;

        if (($this->lastOpponentByTeam[$home] ?? null) === $away || ($this->lastOpponentByTeam[$away] ?? null) === $home) {
            $roundNumber = $roundIndex + 1;
            $this->violations[] = "Round {$roundNumber}: {$this->context->teamLabel($home)} and {$this->context->teamLabel($away)} played each other in consecutive rounds.";
        }

        $this->lastOpponentByTeam[$home] = $away;
        $this->lastOpponentByTeam[$away] = $home;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        unset($this->lastOpponentByTeam[$teamId]);
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
