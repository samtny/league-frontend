<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class InactiveVenueConstraint implements HardConstraint
{
    /** @var string[] */
    private array $violations = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'inactive_venue';
    }

    public function label(): string
    {
        return 'Venue not in the active pool';
    }

    public function startRound(int $roundIndex): void
    {
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        if (! isset($this->context->activeVenueIds[$match->venueId])) {
            $roundNumber = $roundIndex + 1;
            $this->violations[] = "Round {$roundNumber}: inactive or unknown venue #{$match->venueId} was used.";
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
