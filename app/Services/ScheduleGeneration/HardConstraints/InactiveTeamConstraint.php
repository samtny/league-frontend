<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class InactiveTeamConstraint implements HardConstraint
{
    /** @var string[] */
    private array $violations = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'inactive_team';
    }

    public function label(): string
    {
        return 'Team not in the active roster';
    }

    public function startRound(int $roundIndex): void
    {
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;

        foreach ([$match->homeTeamId, $match->awayTeamId] as $teamId) {
            if (! isset($this->context->activeTeamIds[$teamId])) {
                $this->violations[] = "Round {$roundNumber}: inactive or unknown team #{$teamId} was assigned a match.";
            }
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
